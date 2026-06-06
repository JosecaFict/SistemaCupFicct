import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Select } from "../../components/ui/Select";
import { Badge } from "../../components/ui/Badge";
import { StatCard } from "../../components/cards/StatCard";
import { Spinner } from "../../components/ui/Spinner";
import { api } from "../../services/api";
import { notasService } from "../../services/notasService";

/*
 * CoordinadorDashboard (Ciclo 2)
 * --------------------------------------------------------------------------
 * Panel del coordinador: supervision del proceso y validacion de notas.
 *   - KPIs del proceso (inscritos / aprobados / reprobados / grupos).
 *   - KPI propio del rol: bloques de notas pendientes de validar.
 *   - Detalle de la gestion y accesos rapidos a sus modulos.
 * Reusa /api/dashboard/* (abierto a autenticados) y el endpoint de
 * bloques pendientes (COORDINADOR + ADMINISTRADOR).
 */
interface GestionLite {
  id: number;
  codigo: string;
  nombre: string;
  estado: "BORRADOR" | "ACTIVA" | "CERRADA";
}

interface DashboardData {
  gestion: {
    id: number;
    codigo: string;
    nombre: string;
    estado: string;
    fecha_inicio_preinscripcion: string;
    fecha_cierre_preinscripcion: string;
    cantidad_examenes: number;
    capacidad_maxima_grupo: number;
    nota_minima_aprobacion: string | number;
    estimado_postulantes: number;
    turnos_habilitados: string;
  } | null;
  kpis: {
    total_inscritos: number;
    total_aprobados: number;
    total_reprobados: number;
    total_grupos_habilitados: number;
    total_sin_cupo: number;
  };
  postulaciones: {
    total: number;
    preinscritos: number;
    form_generado: number;
    pago_aprobado: number;
    observados: number;
    inscritos: number;
    anulados: number;
    aceptados: number;
    reprobados: number;
    sin_cupo: number;
  };
}

const fmtFecha = (iso?: string | null) =>
  iso ? iso.slice(0, 10).split("-").reverse().join("/") : "-";

export function CoordinadorDashboard() {
  const [gestiones, setGestiones] = useState<GestionLite[]>([]);
  const [gestionId, setGestionId] = useState<number | "">("");
  const [data, setData] = useState<DashboardData | null>(null);
  const [cargando, setCargando] = useState(false);
  const [bloquesPendientes, setBloquesPendientes] = useState<number | null>(null);

  // Lista de gestiones para el selector.
  useEffect(() => {
    api.get<GestionLite[]>("/api/dashboard/gestiones").then((r) => setGestiones(r.data));
  }, []);

  // Resumen segun gestion seleccionada.
  useEffect(() => {
    setCargando(true);
    api
      .get<DashboardData>("/api/dashboard/resumen", {
        params: gestionId ? { gestion_id: gestionId } : {},
      })
      .then((r) => setData(r.data))
      .finally(() => setCargando(false));
  }, [gestionId]);

  // Bloques de notas pendientes de la gestion resuelta (KPI propio del rol).
  useEffect(() => {
    const gid = data?.gestion?.id;
    if (!gid) {
      setBloquesPendientes(null);
      return;
    }
    notasService
      .bloquesPendientes(gid)
      .then((bloques) => setBloquesPendientes(bloques.filter((b) => b.pendientes > 0).length))
      .catch(() => setBloquesPendientes(null));
  }, [data?.gestion?.id]);

  if (!data) return <div className="flex justify-center py-10"><Spinner /></div>;

  const g = data.gestion;
  const k = data.kpis;

  return (
    <div className="space-y-6">
      {/* Header con selector de gestion */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-institutional-800">Panel coordinador</h1>
          <p className="text-sm text-muted-500">
            Supervision del proceso CUP y validacion de notas.
          </p>
        </div>
        <div className="min-w-[260px]">
          <label className="text-xs text-muted-500">Gestion</label>
          <Select
            value={String(gestionId)}
            onChange={(e) => setGestionId(e.target.value ? Number(e.target.value) : "")}
          >
            <option value="">Automatica (activa o ultima)</option>
            {gestiones.map((gg) => (
              <option key={gg.id} value={gg.id}>
                {gg.codigo}  {gg.nombre} ({gg.estado})
              </option>
            ))}
          </Select>
        </div>
      </div>

      {/* KPIs del proceso */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Total inscritos"
          value={cargando ? "..." : k.total_inscritos}
          helper="Postulantes inscritos en la gestion"
        />
        <StatCard
          label="Total aprobados"
          value={cargando ? "..." : k.total_aprobados}
          accent="success"
          helper="Aceptados en alguna carrera"
        />
        <StatCard
          label="Total reprobados"
          value={cargando ? "..." : k.total_reprobados}
          accent="warning"
          helper="No alcanzaron la nota minima"
        />
        <StatCard
          label="Bloques de notas por validar"
          value={cargando ? "..." : (bloquesPendientes ?? 0)}
          accent="institutional"
          helper="Grupo + materia + examen con notas pendientes"
        />
      </div>

      {/* Info de la gestion seleccionada */}
      {g ? (
        <Card title="Gestion seleccionada">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
              <div className="text-xs uppercase text-muted-500 tracking-wide">Codigo</div>
              <div className="font-semibold text-institutional-800">{g.codigo}</div>
              <div className="text-xs text-muted-500">{g.nombre}</div>
            </div>
            <div>
              <div className="text-xs uppercase text-muted-500 tracking-wide">Estado</div>
              <Badge tone={g.estado === "ACTIVA" ? "success" : g.estado === "CERRADA" ? "neutral" : "info"}>
                {g.estado}
              </Badge>
            </div>
            <div>
              <div className="text-xs uppercase text-muted-500 tracking-wide">Periodo de preinscripcion</div>
              <div>{fmtFecha(g.fecha_inicio_preinscripcion)} a {fmtFecha(g.fecha_cierre_preinscripcion)}</div>
            </div>
            <div>
              <div className="text-xs uppercase text-muted-500 tracking-wide">Examenes</div>
              <div>{g.cantidad_examenes}</div>
            </div>
            <div>
              <div className="text-xs uppercase text-muted-500 tracking-wide">Nota minima de aprobacion</div>
              <div>{g.nota_minima_aprobacion}</div>
            </div>
            <div>
              <div className="text-xs uppercase text-muted-500 tracking-wide">Grupos habilitados</div>
              <div>{k.total_grupos_habilitados}</div>
            </div>
          </div>
        </Card>
      ) : (
        <Card title="Sin gestion seleccionada">
          <div className="text-sm text-muted-500">
            No hay gestiones disponibles todavia.
          </div>
        </Card>
      )}

      {/* Desglose de resultados */}
      <Card title="Resultados del proceso">
        <ul className="text-sm space-y-1">
          <li>Inscritos: <b className="text-institutional-700">{data.postulaciones.inscritos}</b></li>
          <li className="pt-1 border-t border-muted-100">Aceptados: <b className="text-success-600">{data.postulaciones.aceptados}</b></li>
          <li>Reprobados: <b className="text-warning-600">{data.postulaciones.reprobados}</b></li>
          <li>Sin cupo: <b className="text-warning-600">{data.postulaciones.sin_cupo}</b></li>
        </ul>
      </Card>

      {/* Accesos rapidos del coordinador */}
      <Card title="Accesos rapidos">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
          <Link to="/coordinador/notas-pendientes" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Notas pendientes</div>
            <div className="text-xs text-muted-500 mt-1">Validar o rechazar bloques</div>
          </Link>
          <Link to="/coordinador/asignaciones" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Asignaciones docente</div>
            <div className="text-xs text-muted-500 mt-1">Asignar grupos y materias</div>
          </Link>
          <Link to="/coordinador/calculo" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Calculo de resultados</div>
            <div className="text-xs text-muted-500 mt-1">Procesar y publicar</div>
          </Link>
          <Link to="/coordinador/resultados" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Resultados</div>
            <div className="text-xs text-muted-500 mt-1">Aprobados y sin cupo</div>
          </Link>
          <Link to="/coordinador/reportes" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Reportes</div>
            <div className="text-xs text-muted-500 mt-1">Listados y estadisticas</div>
          </Link>
        </div>
      </Card>
    </div>
  );
}
