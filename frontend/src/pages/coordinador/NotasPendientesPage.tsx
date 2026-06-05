import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Badge } from "../../components/ui/Badge";
import { Modal } from "../../components/ui/Modal";
import { Alert } from "../../components/ui/Alert";
import { Select } from "../../components/ui/Select";
import { Spinner } from "../../components/ui/Spinner";
import { DataTable } from "../../components/tables/DataTable";
import { api } from "../../services/api";
import { notasService, type BloquePendiente } from "../../services/notasService";

/*
 * NotasPendientesPage (Ciclo 2 - CU15)
 * --------------------------------------------------------------------------
 * Para COORDINADOR y ADMINISTRADOR.
 * Muestra los bloques de notas con al menos una nota PENDIENTE.
 * Permite validar el bloque entero o rechazarlo con observacion.
 */
interface GestionLite { id: number; codigo: string; nombre: string; estado: string }

export function NotasPendientesPage() {
  const [gestiones, setGestiones] = useState<GestionLite[]>([]);
  const [gestionId, setGestionId] = useState<number | "">("");
  const [bloques, setBloques] = useState<BloquePendiente[]>([]);
  const [cargando, setCargando] = useState(false);
  const [accion, setAccion] = useState<null | {
    tipo: "validar" | "rechazar";
    bloque: BloquePendiente;
  }>(null);
  const [observacion, setObservacion] = useState("");
  const [aplicando, setAplicando] = useState(false);
  const [mensaje, setMensaje] = useState<string | null>(null);

  useEffect(() => {
    api.get<GestionLite[]>("/api/dashboard/gestiones").then((r) => {
      setGestiones(r.data);
      if (!gestionId && r.data.length > 0) setGestionId(r.data[0].id);
    });
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!gestionId) return;
    recargar();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [gestionId]);

  function recargar() {
    if (!gestionId) return;
    setCargando(true);
    notasService.bloquesPendientes(gestionId as number)
      .then(setBloques)
      .finally(() => setCargando(false));
  }

  async function aplicar() {
    if (!accion) return;
    const { tipo, bloque } = accion;
    const payload = {
      gestion_id: gestionId as number,
      grupo_id: bloque.grupo_id,
      gestion_materia_id: bloque.gestion_materia_id,
      numero_examen: bloque.numero_examen,
    };
    setAplicando(true);
    try {
      if (tipo === "validar") {
        const r = await notasService.validarBloque(payload);
        setMensaje(r.mensaje);
      } else {
        if (observacion.trim().length < 5) {
          setMensaje("La observacion es obligatoria (minimo 5 caracteres).");
          setAplicando(false);
          return;
        }
        const r = await notasService.rechazarBloque({ ...payload, observacion: observacion.trim() });
        setMensaje(r.mensaje);
      }
      setAccion(null);
      setObservacion("");
      recargar();
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    } catch (e: any) {
      setMensaje(e?.response?.data?.message ?? "Error al aplicar accion.");
    } finally {
      setAplicando(false);
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-institutional-800">Notas pendientes de validar</h1>
          <p className="text-sm text-muted-500">
            Cada bloque agrupa las notas de un grupo+materia+examen.
            Validar valida TODO el bloque; rechazar lo devuelve al docente.
          </p>
        </div>
        <div className="min-w-[260px]">
          <label className="text-xs text-muted-500">Gestion</label>
          <Select
            value={String(gestionId)}
            onChange={(e) => setGestionId(e.target.value ? Number(e.target.value) : "")}
          >
            {gestiones.map((g) => (
              <option key={g.id} value={g.id}>{g.codigo} ({g.estado})</option>
            ))}
          </Select>
        </div>
      </div>

      {mensaje && <Alert tone="success">{mensaje}</Alert>}

      {cargando ? (
        <div className="flex justify-center py-10"><Spinner /></div>
      ) : (
        <Card>
          <DataTable
            rows={bloques}
            empty="No hay bloques pendientes en esta gestion."
            columns={[
              { header: "Grupo", cell: (b: BloquePendiente) => (
                  <span className="font-mono font-semibold">{b.grupo_codigo}</span>
                ) },
              { header: "Turno", cell: (b: BloquePendiente) => b.turno_codigo },
              { header: "Materia", cell: (b: BloquePendiente) => (
                  <Badge tone="info">{b.materia_codigo}</Badge>
                ) },
              { header: "Examen", cell: (b: BloquePendiente) => `Examen ${b.numero_examen}` },
              { header: "Docente", cell: (b: BloquePendiente) => b.docente ?? "-" },
              { header: "Estado del bloque", cell: (b: BloquePendiente) => (
                  <div className="space-y-1">
                    <div>{b.pendientes} pendientes</div>
                    {b.validadas > 0 && <div className="text-xs text-success-700">{b.validadas} validadas</div>}
                    {b.rechazadas > 0 && <div className="text-xs text-danger-600">{b.rechazadas} rechazadas</div>}
                  </div>
                ) },
              { header: "Acciones", cell: (b: BloquePendiente) => (
                  <div className="flex gap-1">
                    <Button size="sm" variant="success" onClick={() => setAccion({ tipo: "validar", bloque: b })}>
                      Validar
                    </Button>
                    <Button size="sm" variant="danger" onClick={() => setAccion({ tipo: "rechazar", bloque: b })}>
                      Rechazar
                    </Button>
                  </div>
                ) },
            ]}
          />
        </Card>
      )}

      {accion && (
        <Modal
          open
          onClose={() => { setAccion(null); setObservacion(""); }}
          title={accion.tipo === "validar" ? "Validar bloque" : "Rechazar bloque"}
          footer={
            <>
              <Button variant="secondary" onClick={() => { setAccion(null); setObservacion(""); }}>Cancelar</Button>
              <Button
                variant={accion.tipo === "validar" ? "success" : "danger"}
                onClick={aplicar}
                disabled={aplicando}
              >
                {aplicando ? "Aplicando..." : accion.tipo === "validar" ? "Validar todo" : "Rechazar todo"}
              </Button>
            </>
          }
        >
          <div className="space-y-3 text-sm">
            <div>
              Vas a {accion.tipo === "validar" ? <b>validar</b> : <b>rechazar</b>} todas las notas
              {" "}pendientes del bloque:
            </div>
            <div className="bg-muted-50 border border-muted-100 rounded p-3 font-mono text-xs">
              {accion.bloque.grupo_codigo}  {accion.bloque.materia_codigo}  Examen {accion.bloque.numero_examen}
              <br />
              Docente: {accion.bloque.docente ?? "-"}
              <br />
              Pendientes a procesar: {accion.bloque.pendientes}
            </div>
            {accion.tipo === "rechazar" && (
              <div>
                <label className="text-xs text-muted-500">Motivo del rechazo (obligatorio)</label>
                <textarea
                  className="w-full border border-muted-200 rounded px-2 py-1 text-sm"
                  rows={3}
                  value={observacion}
                  onChange={(e) => setObservacion(e.target.value)}
                  placeholder="Ej: nota fuera de rango razonable en varios estudiantes."
                />
                <div className="text-xs text-muted-500">
                  El docente vera este motivo al editar las notas.
                </div>
              </div>
            )}
          </div>
        </Modal>
      )}
    </div>
  );
}
