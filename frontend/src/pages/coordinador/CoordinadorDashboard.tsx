import { Card } from "../../components/ui/Card";

// Placeholder para Ciclo 2/5 (reportes y cupos).
export function CoordinadorDashboard() {
  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold text-institutional-800">Panel coordinador</h1>
      <Card title="Reportes y cupos (Ciclo 2 / 5)">
        Las funciones de coordinador (supervision del proceso, reportes, asignacion de cupos)
        llegan en ciclos posteriores. La estructura de rutas, middleware y dashboard ya esta preparada.
      </Card>
    </div>
  );
}
