import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Select } from "../../components/ui/Select";
import { Badge } from "../../components/ui/Badge";
import { Alert } from "../../components/ui/Alert";
import { Modal } from "../../components/ui/Modal";
import { DataTable } from "../../components/tables/DataTable";
import { api } from "../../services/api";
import {
  asignacionesService,
  type AsignacionDocente,
  type CatalogosFiltros,
  type DatosIniciales,
  type RecursosDisponibles,
  type PayloadAsignacion,
} from "../../services/asignacionesService";

/*
 * AsignacionesPage (Ciclo 2 - CU12, CU13)
 * --------------------------------------------------------------------------
 * ADMIN + COORDINADOR pueden crear, editar y borrar asignaciones de docentes
 * a grupos x materias con horario y ambiente.
 *
 * Flujo guiado:
 *   1. Elegir gestion y ver lista de asignaciones existentes.
 *   2. "Nueva asignacion": abre formulario donde primero se elige grupo y
 *      materia, despues dias+horario, despues docente y ambiente (estos
 *      ultimos filtrados por choques de horario).
 */
interface GestionLite { id: number; codigo: string; nombre: string; estado: string }

const DIAS_LABELS: Record<string, string> = {
  LU: "Lu", MA: "Ma", MI: "Mi", JU: "Ju", VI: "Vi", SA: "Sa", DO: "Do",
};

const fmtDias = (csv: string) =>
  csv.split(",").map((d) => DIAS_LABELS[d] ?? d).join(" / ");

const fmtHora = (h: string) => h.slice(0, 5); // "08:00:00" -> "08:00"

export function AsignacionesPage() {
  const [gestiones, setGestiones] = useState<GestionLite[]>([]);
  const [gestionId, setGestionId] = useState<number | "">("");
  const [asignaciones, setAsignaciones] = useState<AsignacionDocente[]>([]);
  const [cargandoLista, setCargandoLista] = useState(false);
  const [filtros, setFiltros] = useState<{
    grupo_id?: number;
    docente_id?: number;
    materia_id?: number;
    turno_id?: number;
  }>({});
  const [catalogos, setCatalogos] = useState<CatalogosFiltros | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [editando, setEditando] = useState<AsignacionDocente | null>(null);

  // Cargar lista de gestiones
  useEffect(() => {
    api.get<GestionLite[]>("/api/dashboard/gestiones").then((r) => {
      setGestiones(r.data);
      if (!gestionId && r.data.length > 0) setGestionId(r.data[0].id);
    });
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Cargar catalogos de filtros al cambiar gestion
  useEffect(() => {
    if (!gestionId) { setCatalogos(null); return; }
    let vigente = true;
    asignacionesService.catalogosFiltros(gestionId as number)
      .then((c) => { if (vigente) setCatalogos(c); });
    return () => { vigente = false; };
  }, [gestionId]);

  // Cargar asignaciones cuando cambia gestion o filtros
  useEffect(() => {
    if (!gestionId) return;
    let vigente = true;
    setCargandoLista(true);
    asignacionesService
      .lista({ gestion_id: gestionId as number, ...filtros })
      .then((r) => { if (vigente) setAsignaciones(r.data); })
      .finally(() => { if (vigente) setCargandoLista(false); });
    return () => { vigente = false; };
  }, [gestionId, filtros]);

  function setFiltro<K extends keyof typeof filtros>(k: K, v: (typeof filtros)[K]) {
    setFiltros((f) => ({ ...f, [k]: v }));
  }

  function recargar() {
    if (!gestionId) return;
    asignacionesService.lista({ gestion_id: gestionId as number, ...filtros }).then((r) => setAsignaciones(r.data));
  }

  async function eliminar(a: AsignacionDocente) {
    if (!confirm(`Borrar asignacion de ${a.grupo?.codigo} - ${a.gestion_materia?.materia?.codigo}?`)) return;
    await asignacionesService.borrar(a.id);
    recargar();
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-institutional-800">Asignaciones docente</h1>
          <p className="text-sm text-muted-500">
            Asigna docentes a grupos por materia, con horario y ambiente.
          </p>
        </div>
        <div className="flex gap-2 items-end">
          <div>
            <label className="text-xs text-muted-500">Gestion</label>
            <Select
              value={String(gestionId)}
              onChange={(e) => {
                setGestionId(e.target.value ? Number(e.target.value) : "");
                setFiltros({});
              }}
            >
              {gestiones.map((g) => (
                <option key={g.id} value={g.id}>
                  {g.codigo}  {g.estado}
                </option>
              ))}
            </Select>
          </div>
          <Button
            onClick={() => { setEditando(null); setModalOpen(true); }}
            disabled={!gestionId}
          >
            + Nueva asignacion
          </Button>
        </div>
      </div>

      {/* Filtros */}
      {catalogos && (
        <Card>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div>
              <label className="text-xs text-muted-500">Turno</label>
              <Select
                value={String(filtros.turno_id ?? "")}
                onChange={(e) => setFiltro("turno_id", e.target.value ? Number(e.target.value) : undefined)}
              >
                <option value="">Todos</option>
                {catalogos.turnos.map((t) => (
                  <option key={t.id} value={t.id}>{t.codigo}  {t.nombre}</option>
                ))}
              </Select>
            </div>
            <div>
              <label className="text-xs text-muted-500">Materia</label>
              <Select
                value={String(filtros.materia_id ?? "")}
                onChange={(e) => setFiltro("materia_id", e.target.value ? Number(e.target.value) : undefined)}
              >
                <option value="">Todas</option>
                {catalogos.materias.map((m) => (
                  <option key={m.materia_id} value={m.materia_id}>{m.codigo}  {m.nombre}</option>
                ))}
              </Select>
            </div>
            <div>
              <label className="text-xs text-muted-500">Docente</label>
              <Select
                value={String(filtros.docente_id ?? "")}
                onChange={(e) => setFiltro("docente_id", e.target.value ? Number(e.target.value) : undefined)}
              >
                <option value="">Todos</option>
                {catalogos.docentes.map((d) => (
                  <option key={d.id} value={d.id}>{d.nombre} {d.apellidos}</option>
                ))}
              </Select>
            </div>
            <div className="flex items-end">
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setFiltros({})}
                disabled={!filtros.turno_id && !filtros.materia_id && !filtros.docente_id}
              >
                Limpiar filtros
              </Button>
            </div>
          </div>
        </Card>
      )}

      <Card>
        <DataTable
          rows={asignaciones}
          empty={cargandoLista ? "Cargando..." : "Sin asignaciones para esta gestion."}
          columns={[
            { header: "Grupo",   cell: (a: AsignacionDocente) => (
                <span className="font-mono font-semibold">{a.grupo?.codigo}</span>
              ) },
            { header: "Turno",   cell: (a: AsignacionDocente) => a.grupo?.turno?.nombre ?? "-" },
            { header: "Materia", cell: (a: AsignacionDocente) => (
                <Badge tone="info">{a.gestion_materia?.materia?.codigo}</Badge>
              ) },
            { header: "Docente", cell: (a: AsignacionDocente) =>
                a.docente ? `${a.docente.nombre} ${a.docente.apellidos}` : "-" },
            { header: "Dias",    cell: (a: AsignacionDocente) => (
                <span className="font-mono">{fmtDias(a.dias_semana)}</span>
              ) },
            { header: "Horario", cell: (a: AsignacionDocente) => (
                <span className="font-mono">{fmtHora(a.hora_inicio)}-{fmtHora(a.hora_fin)}</span>
              ) },
            { header: "Ambiente",cell: (a: AsignacionDocente) => a.ambiente?.nombre ?? "-" },
            { header: "",        cell: (a: AsignacionDocente) => (
                <div className="flex gap-1">
                  <Button size="sm" variant="secondary" onClick={() => { setEditando(a); setModalOpen(true); }}>
                    Editar
                  </Button>
                  <Button size="sm" variant="ghost" onClick={() => eliminar(a)}>
                    Borrar
                  </Button>
                </div>
              ) },
          ]}
        />
        <div className="text-xs text-muted-500 mt-2">
          Mostrando {asignaciones.length} asignaciones.
        </div>
      </Card>

      {modalOpen && gestionId && (
        <AsignacionFormModal
          gestionId={gestionId as number}
          asignacion={editando}
          onClose={() => setModalOpen(false)}
          onSaved={() => { setModalOpen(false); recargar(); }}
        />
      )}
    </div>
  );
}

/* ===================================================================
 * Subcomponente: Modal con el flujo guiado de asignacion
 * =================================================================== */

interface FormState {
  grupo_id: number | "";
  gestion_materia_id: number | "";
  dias_semana: string;
  hora_inicio: string;
  hora_fin: string;
  docente_user_id: number | "";
  ambiente_id: number | "";
}

function AsignacionFormModal({
  gestionId,
  asignacion,
  onClose,
  onSaved,
}: {
  gestionId: number;
  asignacion: AsignacionDocente | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const esEdicion = !!asignacion;

  const [datos, setDatos] = useState<DatosIniciales | null>(null);
  const [recursos, setRecursos] = useState<RecursosDisponibles | null>(null);
  const [cargandoRecursos, setCargandoRecursos] = useState(false);
  const [guardando, setGuardando] = useState(false);
  const [errores, setErrores] = useState<string[]>([]);
  // Asignaciones existentes del grupo seleccionado, para filtrar materias
  // YA usadas en ese grupo apenas se elige el grupo (sin esperar dias/horario).
  const [asignacionesDelGrupo, setAsignacionesDelGrupo] = useState<AsignacionDocente[]>([]);
  // Ciclo 3: si el usuario prefiere escribir horario a mano (Opcion B - escape)
  const [horarioPersonalizado, setHorarioPersonalizado] = useState(false);

  const [form, setForm] = useState<FormState>(() =>
    asignacion
      ? {
          grupo_id: asignacion.grupo_id,
          gestion_materia_id: asignacion.gestion_materia_id,
          dias_semana: asignacion.dias_semana,
          hora_inicio: fmtHora(asignacion.hora_inicio),
          hora_fin: fmtHora(asignacion.hora_fin),
          docente_user_id: asignacion.docente_user_id,
          ambiente_id: asignacion.ambiente_id ?? "",
        }
      : {
          grupo_id: "",
          gestion_materia_id: "",
          dias_semana: "",
          hora_inicio: "",
          hora_fin: "",
          docente_user_id: "",
          ambiente_id: "",
        }
  );

  // Cargar datos iniciales (grupos + materias + bloques sugeridos)
  useEffect(() => {
    let vigente = true;
    asignacionesService.datosIniciales(gestionId)
      .then((d) => { if (vigente) setDatos(d); });
    return () => { vigente = false; };
  }, [gestionId]);

  // Apenas se elige un grupo, traer sus asignaciones existentes para saber
  // que materias ya tiene y excluirlas del dropdown. Esto se hace ANTES de
  // elegir dias/horario; recursosDisponibles tambien filtra, pero recien
  // despues de tener la franja completa.
  useEffect(() => {
    if (!form.grupo_id) { setAsignacionesDelGrupo([]); return; }
    let vigente = true;
    asignacionesService
      .lista({ gestion_id: gestionId, grupo_id: form.grupo_id as number })
      .then((r) => { if (vigente) setAsignacionesDelGrupo(r.data); });
    return () => { vigente = false; };
  }, [form.grupo_id, gestionId]);

  // Cuando hay franja completa (grupo + materia + dias + horario), pedir
  // recursos disponibles. Ciclo 3: enviamos gestion_materia_id para que el
  // backend filtre por habilitacion docente<->materia.
  useEffect(() => {
    if (form.grupo_id && form.gestion_materia_id && form.dias_semana && form.hora_inicio && form.hora_fin) {
      let vigente = true;
      setCargandoRecursos(true);
      asignacionesService
        .recursosDisponibles({
          gestion_id: gestionId,
          dias_semana: form.dias_semana,
          hora_inicio: form.hora_inicio,
          hora_fin: form.hora_fin,
          grupo_id: form.grupo_id as number,
          gestion_materia_id: form.gestion_materia_id as number,
          ignore_id: asignacion?.id,
        })
        .then((r) => { if (vigente) setRecursos(r); })
        .finally(() => { if (vigente) setCargandoRecursos(false); });
      return () => { vigente = false; };
    } else {
      setRecursos(null);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.grupo_id, form.gestion_materia_id, form.dias_semana, form.hora_inicio, form.hora_fin]);

  function setField<K extends keyof FormState>(k: K, v: FormState[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  // Materias del grupo: excluye las ya asignadas.
  // - Si ya tenemos 'recursos' (franja completa) usamos su info -- es la mas
  //   fresca (tiene en cuenta ignore_id en edicion).
  // - Si todavia no hay franja, usamos 'asignacionesDelGrupo' que se carga
  //   apenas el usuario elige el grupo. En edicion, no excluimos la propia.
  const materiasYaAsignadasDelGrupo = asignacionesDelGrupo
    .filter((a) => !asignacion || a.id !== asignacion.id)
    .map((a) => a.gestion_materia_id);

  const materiasDisponibles = (datos?.gestion_materias ?? []).filter((m) => {
    if (recursos) return !recursos.materias_ya_asignadas_al_grupo.includes(m.id);
    return !materiasYaAsignadasDelGrupo.includes(m.id);
  });

  // Turno del grupo seleccionado, para mostrar bloques sugeridos
  const grupoSel = datos?.grupos.find((g) => g.id === form.grupo_id);
  const turnoCodigo = grupoSel?.turno?.codigo;
  const bloquesSugeridos = turnoCodigo && datos
    ? datos.bloques_por_turno[turnoCodigo as "M" | "T" | "N"]
    : [];

  async function guardar() {
    setErrores([]);
    if (!form.grupo_id || !form.gestion_materia_id || !form.docente_user_id
        || !form.dias_semana || !form.hora_inicio || !form.hora_fin) {
      setErrores(["Faltan campos obligatorios."]);
      return;
    }
    const payload: PayloadAsignacion = {
      gestion_cup_id: gestionId,
      grupo_id: form.grupo_id as number,
      gestion_materia_id: form.gestion_materia_id as number,
      docente_user_id: form.docente_user_id as number,
      ambiente_id: form.ambiente_id ? (form.ambiente_id as number) : null,
      dias_semana: form.dias_semana,
      hora_inicio: form.hora_inicio,
      hora_fin: form.hora_fin,
    };
    setGuardando(true);
    try {
      if (esEdicion && asignacion) {
        await asignacionesService.actualizar(asignacion.id, payload);
      } else {
        await asignacionesService.crear(payload);
      }
      onSaved();
    } catch (e: unknown) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const msg = (e as any)?.response?.data?.errors?.conflicto
              // eslint-disable-next-line @typescript-eslint/no-explicit-any
              ?? Object.values((e as any)?.response?.data?.errors ?? {}).flat()
              // eslint-disable-next-line @typescript-eslint/no-explicit-any
              ?? [(e as any)?.response?.data?.message ?? "Error al guardar."];
      setErrores(Array.isArray(msg) ? msg as string[] : [String(msg)]);
    } finally {
      setGuardando(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={esEdicion ? "Editar asignacion" : "Nueva asignacion docente"}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancelar</Button>
          <Button onClick={guardar} disabled={guardando}>
            {guardando ? "Guardando..." : esEdicion ? "Actualizar" : "Crear"}
          </Button>
        </>
      }
    >
      {!datos ? (
        <div className="py-6 text-center text-sm text-muted-500">Cargando...</div>
      ) : (
        <div className="space-y-3 text-sm">
          {errores.length > 0 && (
            <Alert tone="danger">
              <ul className="list-disc list-inside">
                {errores.map((m, i) => <li key={i}>{m}</li>)}
              </ul>
            </Alert>
          )}

          {/* Paso 1: grupo */}
          <div>
            <label className="text-xs text-muted-500">Grupo</label>
            <Select
              value={String(form.grupo_id)}
              onChange={(e) => setField("grupo_id", e.target.value ? Number(e.target.value) : "")}
            >
              <option value="">Selecciona un grupo</option>
              {datos.grupos.map((g) => (
                <option key={g.id} value={g.id}>
                  {g.codigo} ({g.turno?.nombre})  capacidad {g.capacidad}
                </option>
              ))}
            </Select>
          </div>

          {/* Paso 2: materia (filtrada por las que faltan en el grupo) */}
          <div>
            <label className="text-xs text-muted-500">Materia</label>
            <Select
              value={String(form.gestion_materia_id)}
              onChange={(e) => setField("gestion_materia_id", e.target.value ? Number(e.target.value) : "")}
              disabled={!form.grupo_id}
            >
              <option value="">Selecciona una materia</option>
              {materiasDisponibles.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.materia?.codigo}  {m.materia?.nombre}
                </option>
              ))}
            </Select>
            {form.grupo_id && materiasDisponibles.length === 0 && (
              <div className="text-xs text-warning-600 mt-1">
                Este grupo ya tiene todas las materias asignadas.
              </div>
            )}
          </div>

          {/* Paso 3: dias */}
          <div>
            <label className="text-xs text-muted-500">Dias de clase</label>
            <Select
              value={form.dias_semana}
              onChange={(e) => setField("dias_semana", e.target.value)}
            >
              <option value="">Selecciona los dias</option>
              {Object.entries(datos.patrones_dias).map(([csv, label]) => (
                <option key={csv} value={csv}>{label}</option>
              ))}
            </Select>
          </div>

          {/* Paso 4: horario -- Ciclo 3 Opcion B (tarjetas + escape personalizado) */}
          <div>
            <label className="text-xs text-muted-500 block mb-1">
              Horario del turno {turnoCodigo && <span className="text-institutional-600 font-medium">({turnoCodigo})</span>}
            </label>
            {bloquesSugeridos.length > 0 ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {bloquesSugeridos.map((b, i) => {
                  const seleccionado = form.hora_inicio === b[0] && form.hora_fin === b[1];
                  return (
                    <button
                      key={i}
                      type="button"
                      onClick={() => {
                        setField("hora_inicio", b[0]);
                        setField("hora_fin", b[1]);
                        setHorarioPersonalizado(false);
                      }}
                      className={`rounded-md border-2 px-3 py-3 text-left transition ${
                        seleccionado
                          ? "border-institutional-500 bg-institutional-50 ring-1 ring-institutional-300"
                          : "border-muted-200 bg-white hover:border-institutional-300 hover:bg-institutional-50/40"
                      }`}
                    >
                      <div className="text-xs uppercase font-medium text-muted-500">
                        Bloque {i + 1}
                      </div>
                      <div className="text-base font-mono font-semibold text-institutional-800">
                        {b[0]} — {b[1]}
                      </div>
                      {seleccionado && (
                        <div className="text-xs text-institutional-600 mt-1">✓ Seleccionado</div>
                      )}
                    </button>
                  );
                })}
              </div>
            ) : (
              <div className="text-xs text-muted-500 border border-muted-200 rounded p-2">
                Elige primero un grupo para ver los bloques sugeridos del turno.
              </div>
            )}

            <div className="mt-2">
              <button
                type="button"
                className="text-xs text-institutional-600 hover:underline"
                onClick={() => setHorarioPersonalizado((v) => !v)}
              >
                {horarioPersonalizado ? "− Ocultar horario personalizado" : "+ Horario personalizado"}
              </button>
            </div>

            {horarioPersonalizado && (
              <div className="flex gap-2 mt-2 items-center bg-muted-50/60 border border-muted-200 rounded p-2">
                <span className="text-xs text-muted-500">De</span>
                <input
                  type="time"
                  value={form.hora_inicio}
                  onChange={(e) => setField("hora_inicio", e.target.value)}
                  className="border border-muted-200 rounded px-2 py-1 text-sm"
                />
                <span className="text-xs text-muted-500">a</span>
                <input
                  type="time"
                  value={form.hora_fin}
                  onChange={(e) => setField("hora_fin", e.target.value)}
                  className="border border-muted-200 rounded px-2 py-1 text-sm"
                />
              </div>
            )}
          </div>

          {/* Paso 5: docente (filtrado por choque + habilitacion + carga) */}
          <div>
            <label className="text-xs text-muted-500">
              Docente
              {cargandoRecursos && <span className="text-muted-500"> (calculando disponibilidad...)</span>}
            </label>
            <Select
              value={String(form.docente_user_id)}
              onChange={(e) => setField("docente_user_id", e.target.value ? Number(e.target.value) : "")}
              disabled={!recursos}
            >
              <option value="">
                {recursos ? "Selecciona un docente disponible" : "Completa grupo, materia, dias y horario"}
              </option>
              {recursos?.docentes_disponibles.map((d) => {
                const usadas = d.asignaciones_usadas ?? 0;
                const max = d.asignaciones_maximo ?? recursos.max_asignaciones_docente ?? 4;
                return (
                  <option key={d.id} value={d.id}>
                    {d.nombre} {d.apellidos} · ({usadas}/{max}) · {d.email}
                  </option>
                );
              })}
            </Select>
            {recursos && recursos.docentes_disponibles.length === 0 && (
              <div className="text-xs text-warning-600 mt-1">
                No hay docentes disponibles: revisar habilitacion, carga (max 4) o horario.
              </div>
            )}
          </div>

          {/* Paso 6: ambiente (opcional, filtrado) */}
          <div>
            <label className="text-xs text-muted-500">Ambiente (opcional)</label>
            <Select
              value={String(form.ambiente_id)}
              onChange={(e) => setField("ambiente_id", e.target.value ? Number(e.target.value) : "")}
              disabled={!recursos}
            >
              <option value="">Sin ambiente asignado</option>
              {recursos?.ambientes_disponibles.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.nombre}{a.ubicacion ? `  ${a.ubicacion}` : ""}
                </option>
              ))}
            </Select>
          </div>

          {/* Aviso si el grupo esta ocupado en esa franja */}
          {recursos && form.grupo_id
              && recursos.grupos_ocupados_en_franja.includes(form.grupo_id as number) && (
            <Alert tone="warning">
              Este grupo ya tiene otra materia asignada en este dia y horario.
              Cambia los dias o el horario.
            </Alert>
          )}
        </div>
      )}
    </Modal>
  );
}
