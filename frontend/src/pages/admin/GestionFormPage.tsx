import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Select } from "../../components/ui/Select";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { FormSection } from "../../components/forms/FormSection";
import { adminService } from "../../services/adminService";
import { api } from "../../services/api";
import type { Carrera, Materia } from "../../types";
import { useToast } from "../../hooks/useToast";

/*
 * GestionFormPage (CU3, CU11)
 * --------------------------------------------------------------------------
 * Crea / edita una gestion CUP completa: fechas de preinscripcion, examenes,
 * ponderaciones de materias, cupos por carrera, capacidad, estimado, turnos.
 * Incluye boton para generar grupos automaticamente (CU11).
 */
export function GestionFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { push } = useToast();
  const editando = Boolean(id);

  const [carreras, setCarreras] = useState<Carrera[]>([]);
  const [materias, setMaterias] = useState<Materia[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [guardando, setGuardando] = useState(false);
  const [generando, setGenerando] = useState(false);

  const [g, setG] = useState({
    codigo: "", nombre: "",
    fecha_inicio_preinscripcion: "",
    fecha_cierre_preinscripcion: "",
    cantidad_examenes: "2",
    capacidad_maxima_grupo: "40",
    estimado_postulantes: "100",
    turnos_habilitados: "M,T,N",
    estado: "BORRADOR",
  });
  const [fechas, setFechas]   = useState<{ numero: number; fecha: string; ponderacion: number }[]>([]);
  const [pondMat, setPondMat] = useState<Record<number, number>>({});
  const [cupos, setCupos]     = useState<Record<number, number>>({});

  useEffect(() => {
    api.get<Carrera[]>("/api/catalogos/carreras").then((r) => setCarreras(r.data));
    api.get<Materia[]>("/api/catalogos/materias").then((r) => setMaterias(r.data));
    if (editando && id) {
      adminService.gestion(Number(id)).then((data) => {
        const ge = data as unknown as Record<string, unknown> & {
          fechas_examenes?: { numero: number; fecha: string; ponderacion: number }[];
          gestion_materias?: { materia_id: number; ponderacion: number }[];
          cupos_carrera?: { carrera_id: number; cupos: number }[];
        };
        setG({
          codigo: String(ge.codigo),
          nombre: String(ge.nombre),
          fecha_inicio_preinscripcion: String(ge.fecha_inicio_preinscripcion).slice(0, 10),
          fecha_cierre_preinscripcion: String(ge.fecha_cierre_preinscripcion).slice(0, 10),
          cantidad_examenes: String(ge.cantidad_examenes),
          capacidad_maxima_grupo: String(ge.capacidad_maxima_grupo),
          estimado_postulantes: String(ge.estimado_postulantes),
          turnos_habilitados: String(ge.turnos_habilitados),
          estado: String(ge.estado),
        });
        setFechas(ge.fechas_examenes ?? []);
        setPondMat(Object.fromEntries((ge.gestion_materias ?? []).map((m) => [m.materia_id, m.ponderacion])));
        setCupos(Object.fromEntries((ge.cupos_carrera ?? []).map((c) => [c.carrera_id, c.cupos])));
      });
    } else {
      // Default: 2 examenes
      setFechas([
        { numero: 1, fecha: "", ponderacion: 50 },
        { numero: 2, fecha: "", ponderacion: 50 },
      ]);
    }
  }, [id, editando]);

  // Cuando cambia cantidad de examenes, ajustar el array de fechas
  useEffect(() => {
    const cant = Number(g.cantidad_examenes);
    setFechas((curr) => {
      const arr = [...curr];
      while (arr.length < cant) arr.push({ numero: arr.length + 1, fecha: "", ponderacion: 0 });
      return arr.slice(0, cant).map((f, i) => ({ ...f, numero: i + 1 }));
    });
  }, [g.cantidad_examenes]);

  const onGuardar = async () => {
    setError(null);
    setGuardando(true);
    try {
      const payload = {
        ...g,
        cantidad_examenes: Number(g.cantidad_examenes),
        capacidad_maxima_grupo: Number(g.capacidad_maxima_grupo),
        estimado_postulantes: Number(g.estimado_postulantes),
        fechas_examenes: fechas,
        materias: materias.map((m) => ({ materia_id: m.id, ponderacion: Number(pondMat[m.id] ?? 0) })),
        cupos_carrera: carreras.map((c) => ({ carrera_id: c.id, cupos: Number(cupos[c.id] ?? 0) })),
      };
      const r = editando
        ? await adminService.actualizarGestion(Number(id), payload)
        : await adminService.crearGestion(payload);
      push("Gestion guardada", "success");
      navigate(`/admin/gestiones/${r.id}`);
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      setError(er?.response?.data?.message ?? "Error al guardar la gestion.");
    } finally {
      setGuardando(false);
    }
  };

  const onGenerarGrupos = async () => {
    if (!id) return;
    if (!confirm("Generar grupos automaticamente para esta gestion?")) return;
    setGenerando(true);
    try {
      const r = await adminService.generarGrupos(Number(id), false);
      push(`Grupos generados: ${Object.entries(r.resumen).map(([k,v]) => `${k}:${v}`).join(" ")}`, "success");
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      push(er?.response?.data?.message ?? "Error al generar grupos", "danger");
    } finally {
      setGenerando(false);
    }
  };

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-semibold text-institutional-800">
          {editando ? `Gestion ${g.codigo}` : "Nueva gestion CUP"}
        </h1>
        {editando && <Button variant="success" onClick={onGenerarGrupos} loading={generando}>Generar grupos automaticamente</Button>}
      </div>

      {error && <Alert tone="danger">{error}</Alert>}

      <FormSection title="Identificacion">
        <Input label="Codigo (ej. 1-2026)" value={g.codigo} onChange={(e) => setG({ ...g, codigo: e.target.value })} />
        <Input label="Nombre"              value={g.nombre} onChange={(e) => setG({ ...g, nombre: e.target.value })} />
        <Select label="Estado" value={g.estado} onChange={(e) => setG({ ...g, estado: e.target.value })}>
          <option value="BORRADOR">Borrador</option>
          <option value="ACTIVA">Activa</option>
          <option value="CERRADA">Cerrada</option>
        </Select>
      </FormSection>

      <FormSection title="Fechas de preinscripcion">
        <Input label="Inicio" type="date" value={g.fecha_inicio_preinscripcion} onChange={(e) => setG({ ...g, fecha_inicio_preinscripcion: e.target.value })} />
        <Input label="Cierre" type="date" value={g.fecha_cierre_preinscripcion} onChange={(e) => setG({ ...g, fecha_cierre_preinscripcion: e.target.value })} />
      </FormSection>

      <FormSection title="Examenes">
        <Select label="Cantidad" value={g.cantidad_examenes} onChange={(e) => setG({ ...g, cantidad_examenes: e.target.value })}>
          <option value="2">2 examenes</option>
          <option value="3">3 examenes</option>
        </Select>
        <div className="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
          {fechas.map((f, i) => (
            <div key={f.numero} className="border border-muted-100 rounded p-3">
              <div className="text-xs uppercase text-muted-500 mb-1">Examen {f.numero}</div>
              <Input label="Fecha" type="date" value={f.fecha}
                     onChange={(e) => { const c=[...fechas]; c[i]={ ...f, fecha: e.target.value }; setFechas(c); }} />
              <Input label="Ponderacion %" type="number" value={f.ponderacion}
                     onChange={(e) => { const c=[...fechas]; c[i]={ ...f, ponderacion: Number(e.target.value) }; setFechas(c); }} />
            </div>
          ))}
        </div>
      </FormSection>

      <FormSection title="Ponderacion de materias (suma 100%)">
        {materias.map((m) => (
          <Input key={m.id} label={`${m.codigo} - ${m.nombre}`} type="number" min={0} max={100}
                 value={pondMat[m.id] ?? 0}
                 onChange={(e) => setPondMat({ ...pondMat, [m.id]: Number(e.target.value) })} />
        ))}
      </FormSection>

      <FormSection title="Cupos por carrera">
        {carreras.map((c) => (
          <Input key={c.id} label={c.nombre} type="number" min={0}
                 value={cupos[c.id] ?? 0}
                 onChange={(e) => setCupos({ ...cupos, [c.id]: Number(e.target.value) })} />
        ))}
      </FormSection>

      <FormSection title="Capacidad y turnos">
        <Input label="Capacidad maxima por grupo" type="number" min={1} value={g.capacidad_maxima_grupo}
               onChange={(e) => setG({ ...g, capacidad_maxima_grupo: e.target.value })} />
        <Input label="Estimado de postulantes" type="number" min={1} value={g.estimado_postulantes}
               onChange={(e) => setG({ ...g, estimado_postulantes: e.target.value })} />
        <Input label="Turnos habilitados (M,T,N)" value={g.turnos_habilitados}
               onChange={(e) => setG({ ...g, turnos_habilitados: e.target.value.toUpperCase() })}
               hint="Combina M (Manana), T (Tarde), N (Noche)" />
      </FormSection>

      <Card>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={() => navigate("/admin/gestiones")}>Cancelar</Button>
          <Button onClick={onGuardar} loading={guardando}>{editando ? "Guardar cambios" : "Crear gestion"}</Button>
        </div>
      </Card>
    </div>
  );
}
