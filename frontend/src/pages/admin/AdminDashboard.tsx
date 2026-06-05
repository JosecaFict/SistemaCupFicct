import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Select } from "../../components/ui/Select";
import { Badge } from "../../components/ui/Badge";
import { StatCard } from "../../components/cards/StatCard";
import { Spinner } from "../../components/ui/Spinner";
import { api } from "../../services/api";

/*
 * AdminDashboard
 * --------------------------------------------------------------------------
 * Panel administrativo principal. Cumple el requerimiento del documento de
 * la docente:
 *   - Total inscritos
 *   - Total aprobados
 *   - Total reprobados
 *   - Total grupos habilitados
 * Mas extras: detalle de la gestion, estado de pagos y usuarios, accesos
 * rapidos a los modulos clave. Selector de gestion para revisar historicos.
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
    sin_cupo: number;
  };
  pagos: { aprobados: number; pendientes: number; rechazados: number; cancelados: number };
  usuarios: { total: number; activos: number };
}

const fmtFecha = (iso?: string | null) =>
  iso ? iso.slice(0, 10).split("-").reverse().join("/") : "-";

export function AdminDashboard() {
  const [gestiones, setGestiones] = useState<GestionLite[]>([]);
  const [gestionId, setGestionId] = useState<number | "">("");
  const [data, setData] = useState<DashboardData | null>(null);
  const [cargando, setCargando] = useState(false);

  // Cargar lista de gestiones para el selector
  useEffect(() => {
    api.get<GestionLite[]>("/api/dashboard/gestiones").then((r) => setGestiones(r.data));
  }, []);

  // Cargar resumen segun gestion seleccionada
  useEffect(() => {
    setCargando(true);
    api
      .get<DashboardData>("/api/dashboard/resumen", {
        params: gestionId ? { gestion_id: gestionId } : {},
      })
      .then((r) => setData(r.data))
      .finally(() => setCargando(false));
  }, [gestionId]);

  if (!data) return <div className="flex justify-center py-10"><Spinner /></div>;

  const g = data.gestion;
  const k = data.kpis;

  return (
    <div className="space-y-6">
      {/* Header con selector de gestion */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-institutional-800">Panel administrador</h1>
          <p className="text-sm text-muted-500">
            Indicadores estadisticos del proceso CUP.
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

      {/* KPIs obligatorios del documento docente */}
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
          helper="Sin cupo o descalificados"
        />
        <StatCard
          label="Total grupos habilitados"
          value={cargando ? "..." : k.total_grupos_habilitados}
          accent="institutional"
          helper="Grupos activos en la gestion"
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
              <div className="text-xs uppercase text-muted-500 tracking-wide">Capacidad por grupo</div>
              <div>{g.capacidad_maxima_grupo} estudiantes</div>
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
              <div className="text-xs uppercase text-muted-500 tracking-wide">Estimado de postulantes</div>
              <div>{g.estimado_postulantes}</div>
            </div>
            <div>
              <div className="text-xs uppercase text-muted-500 tracking-wide">Turnos habilitados</div>
              <div>{g.turnos_habilitados}</div>
            </div>
          </div>
        </Card>
      ) : (
        <Card title="Sin gestion seleccionada">
          <div className="text-sm text-muted-500">
            No hay gestiones disponibles. Crea una nueva desde el modulo de Gestiones CUP.
          </div>
        </Card>
      )}

      {/* Desglose y accesos rapidos */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card title="Desglose de postulaciones">
          <ul className="text-sm space-y-1">
            <li>Preinscritos: <b>{data.postulaciones.preinscritos}</b></li>
            <li>Con formulario generado: <b>{data.postulaciones.form_generado}</b></li>
            <li>Con pago aprobado: <b>{data.postulaciones.pago_aprobado}</b></li>
            <li>Observados: <b className="text-warning-600">{data.postulaciones.observados}</b></li>
            <li>Inscritos: <b className="text-institutional-700">{data.postulaciones.inscritos}</b></li>
            <li>Anulados: <b>{data.postulaciones.anulados}</b></li>
            <li className="pt-1 border-t border-muted-100">Aceptados: <b className="text-success-600">{data.postulaciones.aceptados}</b></li>
            <li>Sin cupo: <b className="text-warning-600">{data.postulaciones.sin_cupo}</b></li>
          </ul>
        </Card>
        <Card title="Pagos / Usuarios">
          <div className="text-sm space-y-3">
            <div>
              <div className="text-xs uppercase text-muted-500 tracking-wide mb-1">Pagos</div>
              <ul className="space-y-1">
                <li>Aprobados: <b className="text-success-600">{data.pagos.aprobados}</b></li>
                <li>Pendientes: <b>{data.pagos.pendientes}</b></li>
                <li>Rechazados: <b>{data.pagos.rechazados}</b></li>
                <li>Cancelados: <b>{data.pagos.cancelados}</b></li>
              </ul>
            </div>
            <div className="pt-2 border-t border-muted-100">
              <div className="text-xs uppercase text-muted-500 tracking-wide mb-1">Usuarios</div>
              <ul className="space-y-1">
                <li>Total: <b>{data.usuarios.total}</b></li>
                <li>Activos: <b>{data.usuarios.activos}</b></li>
              </ul>
            </div>
          </div>
        </Card>
      </div>

      {/* Accesos rapidos */}
      <Card title="Accesos rapidos">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
          <Link to="/admin/usuarios" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Usuarios y roles</div>
            <div className="text-xs text-muted-500 mt-1">Crear y administrar</div>
          </Link>
          <Link to="/admin/gestiones" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Gestiones CUP</div>
            <div className="text-xs text-muted-500 mt-1">Crear o editar</div>
          </Link>
          <Link to="/admin/postulantes" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Postulantes</div>
            <div className="text-xs text-muted-500 mt-1">Buscar y verificar</div>
          </Link>
          <Link to="/admin/resultados" className="block border border-muted-100 rounded-md p-3 hover:bg-institutional-50 text-center">
            <div className="font-semibold text-institutional-700">Resultados</div>
            <div className="text-xs text-muted-500 mt-1">Aprobados y sin cupo</div>
          </Link>
        </div>
      </Card>
    </div>
  );
}
