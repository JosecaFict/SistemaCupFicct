import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Badge } from "../../components/ui/Badge";
import { Spinner } from "../../components/ui/Spinner";
import { notasService, type MiAsignacion } from "../../services/notasService";

/*
 * DocenteDashboard (Ciclo 2 - CU14)
 * --------------------------------------------------------------------------
 * Muestra las asignaciones del docente autenticado:
 *   - grupo + materia + horario + ambiente
 *   - cuantos postulantes hay y el progreso de notas por examen
 *
 * Cada asignacion enlaza a la pantalla de carga de notas.
 */
const DIAS_LABEL: Record<string, string> = {
  LU: "Lu", MA: "Ma", MI: "Mi", JU: "Ju", VI: "Vi", SA: "Sa", DO: "Do",
};

const fmtDias = (csv: string) =>
  csv.split(",").map((d) => DIAS_LABEL[d] ?? d).join("/");

const fmtHora = (h: string) => h.slice(0, 5);

export function DocenteDashboard() {
  const [data, setData] = useState<MiAsignacion[] | null>(null);

  useEffect(() => {
    notasService.misAsignaciones().then(setData);
  }, []);

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

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold text-institutional-800">Mis asignaciones</h1>
        <p className="text-sm text-muted-500">
          Hac clic en una asignacion para cargar las notas de los postulantes.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {data.map((a) => {
          const numEx = a.numero_examenes || 0;
          return (
            <Card key={a.id}>
              <div className="space-y-2 text-sm">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-mono font-semibold text-institutional-800">
                      {a.grupo?.codigo}  {a.gestion_materia?.materia?.codigo}
                    </div>
                    <div className="text-xs text-muted-500">
                      {a.gestion?.codigo}  {a.gestion_materia?.materia?.nombre}
                    </div>
                  </div>
                  <Badge tone="info">{a.grupo?.turno?.codigo}</Badge>
                </div>

                <div className="text-xs text-muted-500">
                  <div>Horario: {fmtDias(a.dias_semana)} {fmtHora(a.hora_inicio)}-{fmtHora(a.hora_fin)}</div>
                  {a.ambiente && <div>Ambiente: {a.ambiente.nombre}</div>}
                  <div>Postulantes: {a.total_postulantes}</div>
                </div>

                {/* Progreso por examen */}
                <div className="border-t border-muted-100 pt-2">
                  <div className="text-xs uppercase tracking-wide text-muted-500 mb-1">Examenes</div>
                  <div className="flex gap-2 flex-wrap">
                    {Array.from({ length: numEx }, (_, i) => i + 1).map((n) => {
                      const p = a.progreso[String(n)] || a.progreso[n];
                      const cant = p?.cant ?? 0;
                      const validadas = p?.validadas ?? 0;
                      const pendientes = p?.pendientes ?? 0;
                      const tone = validadas === a.total_postulantes && cant > 0
                        ? "success"
                        : pendientes > 0 ? "warning"
                        : cant === 0 ? "neutral" : "info";
                      return (
                        <Link
                          key={n}
                          to={`/docente/notas/${a.grupo?.id}/${a.gestion_materia?.id}/${n}`}
                          className="flex-1 min-w-[80px]"
                        >
                          <div className={`text-xs border rounded-md p-2 text-center hover:bg-institutional-50 ${
                            tone === "success" ? "border-success-200 bg-success-50" :
                            tone === "warning" ? "border-orange-200 bg-orange-50" :
                            tone === "neutral" ? "border-muted-200 bg-muted-50" :
                            "border-institutional-200 bg-institutional-50"
                          }`}>
                            <div className="font-semibold">Examen {n}</div>
                            <div className="text-muted-500">{cant}/{a.total_postulantes}</div>
                            {validadas > 0 && <div className="text-success-700">{validadas} validadas</div>}
                            {pendientes > 0 && <div className="text-warning-600">{pendientes} pendientes</div>}
                          </div>
                        </Link>
                      );
                    })}
                  </div>
                </div>
              </div>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
