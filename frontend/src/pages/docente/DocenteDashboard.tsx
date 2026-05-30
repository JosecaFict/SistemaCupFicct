import { Card } from "../../components/ui/Card";

// Placeholder para Ciclo 2 (modulo academico: cursos y calificaciones).
export function DocenteDashboard() {
  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold text-institutional-800">Panel docente</h1>
      <Card title="Modulo academico (Ciclo 2)">
        Las funciones de docente (cursos, calificaciones por materia) se habilitan en el Ciclo 2.
        La estructura de rutas y middleware ya esta preparada.
      </Card>
    </div>
  );
}
