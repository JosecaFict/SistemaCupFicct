import { useEffect, useState } from "react";
import { StatCard } from "../../components/cards/StatCard";
import { Card } from "../../components/ui/Card";
import { Spinner } from "../../components/ui/Spinner";
import { api } from "../../services/api";

interface Resumen {
  gestion_activa: { codigo: string } | null;
  postulaciones: { total: number; preinscritos: number; form_generado: number; pago_aprobado: number; observados: number; inscritos: number };
}

export function EncargadoDashboard() {
  const [r, setR] = useState<Resumen | null>(null);

  useEffect(() => {
    api.get<Resumen>("/api/dashboard/resumen").then((res) => setR(res.data));
  }, []);

  if (!r) return <div className="flex justify-center py-10"><Spinner /></div>;
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold text-institutional-800">Panel encargado</h1>
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <StatCard label="Pago aprobado"   value={r.postulaciones.pago_aprobado} accent="success" />
        <StatCard label="Form. generado"  value={r.postulaciones.form_generado} />
        <StatCard label="Observados"      value={r.postulaciones.observados} accent="warning" />
        <StatCard label="Inscritos"       value={r.postulaciones.inscritos} accent="success" />
      </div>
      <Card title="Sugerencia">
        Revisa la lista de Postulantes para verificar requisitos y confirmar inscripciones.
      </Card>
    </div>
  );
}
