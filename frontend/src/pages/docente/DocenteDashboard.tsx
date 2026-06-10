import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Badge } from "../../components/ui/Badge";
import { Select } from "../../components/ui/Select";
import { Spinner } from "../../components/ui/Spinner";
import { notasService, type MiAsignacion } from "../../services/notasService";

/*
 * DocenteDashboard (Ciclo 2 - CU16)
 * --------------------------------------------------------------------------
 * Muestra las asignaciones del docente autenticado en una tabla compacta:
 *   Gestion | Materia | Grupo | Turno | Horario | Ambiente | Postulantes | E1 | E2 | E3
 *
 * Cada celda de examen es un enlace a la pantalla de carga de notas.
 */
const DIAS_LABEL: Record<string, string> = {
  LU: "Lu", MA: "Ma", MI: "Mi", JU: "Ju", VI: "Vi", SA: "Sa", DO: "Do",
};

const fmtDias = (csv: string) =>
  csv.split(",").map((d) => DIAS_LABEL[d] ?? d).join("/");

/**
 * Extrae "HH:MM" de cualquier formato (Carbon serializado, "08:00", "08:00:00",
 * "2026-01-01T08:00:00.000000Z"). Si no encuentra patron HH:MM, devuelve "".
 */
const fmtHora = (h: string | null | undefined): string => {
  if (!h) return "";
  const m = String(h).match(/(\d{2}:\d{2})(?!\d)/);
  return m ? m[1] : "";
};

const fmtHorario = (a: MiAsignacion): string => {
  const dias = fmtDias(a.dias_semana);
  const ini = fmtHora(a.hora_inicio);
  const fin = fmtHora(a.hora_fin);
  if (!ini || !fin) return dias;
  return `${dias} ${ini}-${fin}`;
};

type Tono = "success" | "warning" | "neutral" | "info" | "locked";

function tonoExamen(cant: number, validadas: number, pendientes: number, total: number): Tono {
  if (total === 0) return "neutral";
  if (validadas === total && cant > 0) return "success";
  if (pendientes > 0) return "warning";
  if (cant === 0) return "neutral";
  return "info";
}

const TONO_CSS: Record<Tono, string> = {
  success: "border-success-200 bg-success-50 text-success-800 hover:bg-success-100",
  warning: "border-orange-200 bg-orange-50 text-orange-800 hover:bg-orange-100",
  neutral: "border-muted-200 bg-muted-50 text-muted-600 hover:bg-muted-100",
  info:    "border-institutional-200 bg-institutional-50 text-institutional-800 hover:bg-institutional-100",
  locked:  "border-muted-200 bg-muted-100 text-muted-500 cursor-not-allowed",
};

/** "YYYY-MM-DD" -> "DD/MM/YYYY". Devuelve "" si la cadena no encaja. */
function fmtFecha(iso: string | null | undefined): string {
  if (!iso) return "";
  const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : "";
}

export function DocenteDashboard() {
  const [data, setData] = useState<MiAsignacion[] | null>(null);
  const [filtroGestion, setFiltroGestion] = useState<string>("");
  const [filtroTurno, setFiltroTurno]     = useState<string>("");

  useEffect(() => {
    notasService.misAsignaciones().then(setData);
  }, []);

  // Catalogos para los filtros (extraidos de las asignaciones)
  const gestiones = useMemo(() => {
    const set = new Map<string, string>();
    (data ?? []).forEach((a) => {
      if (a.gestion?.codigo) set.set(a.gestion.codigo, a.gestion.codigo);
    });
    return Array.from(set.values()).sort();
  }, [data]);

  const turnos = useMemo(() => {
    const set = new Set<string>();
    (data ?? []).forEach((a) => {
      if (a.grupo?.turno?.codigo) set.add(a.grupo.turno.codigo);
    });
    return Array.from(set).sort();
  }, [data]);

  const filas = useMemo(() => {
    if (!data) return [];
    return data.filter((a) => {
      if (filtroGestion && a.gestion?.codigo !== filtroGestion) return false;
      if (filtroTurno && a.grupo?.turno?.codigo !== filtroTurno) return false;
      return true;
    });
  }, [data, filtroGestion, filtroTurno]);

  if (!data) return <div className="flex justify-center py-10"><Spinner /></div>;

  if (data.length === 0) {
    return (
      <div className="space-y-5">
        <h1 className="text-2xl font-semibold text-institutional-800">Panel docente</h1>
        <Card title="Sin asignaciones">
          Aun no tenes asignaciones de docente. Cuando el coordinador o el
          administrador te asignen un grupo+materia, vas a verlo aca.
        </Card>
      </div>
    );
  }

  // Numero maximo de examenes en cualquier fila (para columnas dinamicas).
  const maxExamenes = data.reduce((m, a) => Math.max(m, a.numero_examenes || 0), 0);
  const numExamenes = Array.from({ length: maxExamenes }, (_, i) => i + 1);

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold text-institutional-800">Mis asignaciones</h1>
        <p className="text-sm text-muted-500">
          Hac clic en un examen para cargar las notas de los postulantes.
        </p>
      </div>

      <Card>
        <div className="flex flex-wrap gap-3 items-end mb-3">
          <div>
            <label className="text-xs text-muted-500">Gestion</label>
            <Select value={filtroGestion} onChange={(e) => setFiltroGestion(e.target.value)}>
              <option value="">Todas</option>
              {gestiones.map((g) => <option key={g} value={g}>{g}</option>)}
            </Select>
          </div>
          <div>
            <label className="text-xs text-muted-500">Turno</label>
            <Select value={filtroTurno} onChange={(e) => setFiltroTurno(e.target.value)}>
              <option value="">Todos</option>
              {turnos.map((t) => <option key={t} value={t}>{t}</option>)}
            </Select>
          </div>
          <div className="text-xs text-muted-500 ml-auto">
            Mostrando {filas.length} de {data.length}
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="bg-muted-50 text-muted-600 text-xs uppercase tracking-wide">
                <th className="px-3 py-2 text-left">Gestion</th>
                <th className="px-3 py-2 text-left">Materia</th>
                <th className="px-3 py-2 text-left">Grupo</th>
                <th className="px-3 py-2 text-center">Turno</th>
                <th className="px-3 py-2 text-left">Horario</th>
                <th className="px-3 py-2 text-left">Ambiente</th>
                <th className="px-3 py-2 text-right">Post.</th>
                {numExamenes.map((n) => (
                  <th key={n} className="px-3 py-2 text-center min-w-[80px]">E{n}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {filas.map((a) => (
                <tr key={a.id} className="border-t border-muted-100 hover:bg-muted-50/40">
                  <td className="px-3 py-2 font-mono text-xs">{a.gestion?.codigo ?? "-"}</td>
                  <td className="px-3 py-2">
                    <Badge tone="info">{a.gestion_materia?.materia?.codigo ?? "-"}</Badge>
                    <span className="ml-1 text-xs text-muted-500">{a.gestion_materia?.materia?.nombre ?? ""}</span>
                  </td>
                  <td className="px-3 py-2 font-mono font-semibold">{a.grupo?.codigo ?? "-"}</td>
                  <td className="px-3 py-2 text-center">{a.grupo?.turno?.codigo ?? "-"}</td>
                  <td className="px-3 py-2 font-mono text-xs">{fmtHorario(a)}</td>
                  <td className="px-3 py-2 text-xs">{a.ambiente?.nombre ?? "-"}</td>
                  <td className="px-3 py-2 text-right font-mono">{a.total_postulantes}</td>
                  {numExamenes.map((n) => {
                    const p = a.progreso[String(n)] || a.progreso[n];
                    const cant       = p?.cant ?? 0;
                    const validadas  = p?.validadas ?? 0;
                    const pendientes = p?.pendientes ?? 0;

                    const fechaIso  = a.fechas_examenes?.[String(n)] ?? null;
                    const habilitado = a.habilitado_carga?.[String(n)] === true;

                    // Si el admin no fijo la fecha o la fecha aun no llego,
                    // mostrar celda bloqueada sin link.
                    if (!habilitado) {
                      const titulo = !fechaIso
                        ? "Fecha del examen aun no definida por el administrador"
                        : `Disponible desde ${fmtFecha(fechaIso)}`;
                      return (
                        <td key={n} className="px-2 py-2 text-center">
                          <span
                            className={`inline-block min-w-[64px] px-2 py-1 rounded border text-xs font-mono ${TONO_CSS.locked}`}
                            title={titulo}
                          >
                            {fechaIso ? `🔒 ${fmtFecha(fechaIso)}` : "🔒 —"}
                          </span>
                        </td>
                      );
                    }

                    const tono = tonoExamen(cant, validadas, pendientes, a.total_postulantes);
                    return (
                      <td key={n} className="px-2 py-2 text-center">
                        <Link
                          to={`/docente/notas/${a.grupo?.id}/${a.gestion_materia?.id}/${n}`}
                          className={`inline-block min-w-[64px] px-2 py-1 rounded border text-xs font-mono ${TONO_CSS[tono]}`}
                          title={
                            validadas === a.total_postulantes && cant > 0
                              ? "Todas validadas"
                              : pendientes > 0
                              ? `${pendientes} pendientes de validacion`
                              : cant === 0
                              ? `Sin notas cargadas (examen del ${fmtFecha(fechaIso)})`
                              : `${cant} cargadas`
                          }
                        >
                          {cant}/{a.total_postulantes}
                          {tono === "success" && <span className="ml-1">✓</span>}
                        </Link>
                      </td>
                    );
                  })}
                </tr>
              ))}
              {filas.length === 0 && (
                <tr>
                  <td colSpan={7 + maxExamenes} className="px-3 py-8 text-center text-muted-500 text-sm">
                    No hay asignaciones que coincidan con los filtros.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="mt-3 flex flex-wrap gap-3 text-xs text-muted-500">
          <span className="inline-flex items-center gap-1">
            <span className="inline-block w-3 h-3 rounded border border-success-200 bg-success-50"></span>
            Todas validadas
          </span>
          <span className="inline-flex items-center gap-1">
            <span className="inline-block w-3 h-3 rounded border border-orange-200 bg-orange-50"></span>
            Con pendientes de validar
          </span>
          <span className="inline-flex items-center gap-1">
            <span className="inline-block w-3 h-3 rounded border border-institutional-200 bg-institutional-50"></span>
            Carga parcial
          </span>
          <span className="inline-flex items-center gap-1">
            <span className="inline-block w-3 h-3 rounded border border-muted-200 bg-muted-50"></span>
            Sin notas
          </span>
          <span className="inline-flex items-center gap-1">
            <span className="inline-block w-3 h-3 rounded border border-muted-200 bg-muted-100"></span>
            🔒 Bloqueado hasta la fecha del examen
          </span>
        </div>
      </Card>
    </div>
  );
}
