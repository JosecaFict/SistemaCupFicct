import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { StatCard } from "../../components/cards/StatCard";
import { Spinner } from "../../components/ui/Spinner";
import { api } from "../../services/api";

interface Resumen {
  gestion_activa: { codigo: string; nombre: string } | null;
  usuarios_total: number;
  usuarios_activos: number;
  postulaciones: { total: number; preinscritos: number; form_generado: number; pago_aprobado: number; observados: number; inscritos: number; anulados: number };
  pagos: { aprobados: number; pendientes: number; rechazados: number };
}

export function AdminDashboard() {
  const [r, setR] = useState<Resumen | null>(null);

  useEffect(() => {
    api.get<Resumen>("/api/dashboard/resumen").then((res) => setR(res.data));
  }, []);

  if (!r) return <div className="flex justify-center py-10"><Spinner /></div>;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-institutional-800">Panel administrador</h1>
        <p className="text-sm text-muted-500">
          Gestion activa: {r.gestion_activa ? <b>{r.gestion_activa.codigo}</b> : <i>ninguna</i>}
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <StatCard label="Postulaciones"   value={r.postulaciones.total} />
        <StatCard label="Inscritos"       value={r.postulaciones.inscritos} accent="success" />
        <StatCard label="Pago aprobado"   value={r.postulaciones.pago_aprobado} accent="success" />
        <StatCard label="Observados"      value={r.postulaciones.observados} accent="warning" />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card title="Pagos">
          <ul className="text-sm space-y-1">
            <li>Aprobados: <b>{r.pagos.aprobados}</b></li>
            <li>Pendientes: <b>{r.pagos.pendientes}</b></li>
            <li>Rechazados: <b>{r.pagos.rechazados}</b></li>
          </ul>
        </Card>
        <Card title="Usuarios">
          <ul className="text-sm space-y-1">
            <li>Totales: <b>{r.usuarios_total}</b></li>
            <li>Activos: <b>{r.usuarios_activos}</b></li>
          </ul>
        </Card>
      </div>
    </div>
  );
}
