import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Select } from "../../components/ui/Select";
import { api } from "../../services/api";

/*
 * ReportesPage (Ciclo 2)
 * --------------------------------------------------------------------------
 * Panel de los 8 reportes obligatorios del documento de la docente.
 * Cada boton descarga el PDF directamente en una pestana nueva.
 *
 * Acceso: ADMINISTRADOR + COORDINADOR.
 */
interface GestionLite { id: number; codigo: string; nombre: string; estado: string }

interface ReporteItem {
  key: string;
  titulo: string;
  descripcion: string;
  endpoint: string;
}

const REPORTES: ReporteItem[] = [
  { key: "lista-general",
    titulo: "Lista general de postulantes",
    descripcion: "Todos los postulantes con sus datos basicos, carreras y estado.",
    endpoint: "/api/reportes/lista-general" },
  { key: "aprobados",
    titulo: "Postulantes aprobados",
    descripcion: "Aceptados con su ranking, carrera asignada y nota final.",
    endpoint: "/api/reportes/aprobados" },
  { key: "reprobados",
    titulo: "Postulantes reprobados",
    descripcion: "No alcanzaron la nota minima, con el motivo de cada caso.",
    endpoint: "/api/reportes/reprobados" },
  { key: "promedios",
    titulo: "Promedios generales",
    descripcion: "KPIs y promedio por carrera con minimo/maximo.",
    endpoint: "/api/reportes/promedios" },
  { key: "grupos",
    titulo: "Grupos habilitados",
    descripcion: "Listado completo con capacidad, inscritos y % ocupacion.",
    endpoint: "/api/reportes/grupos" },
  { key: "estadisticas-materia",
    titulo: "Estadisticas por materia",
    descripcion: "Promedio por materia y examen, con cantidad de descalificantes.",
    endpoint: "/api/reportes/estadisticas-materia" },
  { key: "docentes-grupos",
    titulo: "Docentes por grupos",
    descripcion: "Cada docente con sus asignaciones de grupo, materia, horario y aula.",
    endpoint: "/api/reportes/docentes-grupos" },
  { key: "grupos-aprobados",
    titulo: "Grupos con mayor cantidad de aprobados",
    descripcion: "Ranking de grupos por % de aprobacion (aceptados / inscritos).",
    endpoint: "/api/reportes/grupos-aprobados" },
];

export function ReportesPage() {
  const [gestiones, setGestiones] = useState<GestionLite[]>([]);
  const [gestionId, setGestionId] = useState<number | "">("");
  const [descargando, setDescargando] = useState<string | null>(null);

  useEffect(() => {
    api.get<GestionLite[]>("/api/dashboard/gestiones").then((r) => {
      setGestiones(r.data);
      if (!gestionId && r.data.length > 0) setGestionId(r.data[0].id);
    });
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function descargar(rep: ReporteItem, formato: "pdf" | "excel") {
    if (!gestionId) return;
    const tag = `${rep.key}:${formato}`;
    setDescargando(tag);
    try {
      const res = await api.get(rep.endpoint, {
        params: { gestion_id: gestionId, formato },
        responseType: "blob",
      });
      const tipo = formato === "excel"
        ? "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        : "application/pdf";
      const blob = new Blob([res.data], { type: tipo });
      const url = URL.createObjectURL(blob);
      if (formato === "excel") {
        // Forzar descarga del .xlsx
        const a = document.createElement("a");
        a.href = url;
        a.download = `${rep.key}.xlsx`;
        document.body.appendChild(a);
        a.click();
        a.remove();
      } else {
        // PDF: abrir en nueva pestana para visualizar
        window.open(url, "_blank");
      }
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } finally {
      setDescargando(null);
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-institutional-800">Reportes</h1>
          <p className="text-sm text-muted-500">
            8 reportes obligatorios del documento de la docente. Cada uno se puede
            descargar en PDF o Excel (.xlsx) segun la gestion seleccionada.
          </p>
        </div>
        <div className="min-w-[260px]">
          <label className="text-xs text-muted-500">Gestion</label>
          <Select
            value={String(gestionId)}
            onChange={(e) => setGestionId(e.target.value ? Number(e.target.value) : "")}
          >
            {gestiones.map((g) => (
              <option key={g.id} value={g.id}>{g.codigo}  {g.estado}</option>
            ))}
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {REPORTES.map((r) => (
          <Card key={r.key}>
            <div className="space-y-2">
              <div className="text-base font-semibold text-institutional-800">{r.titulo}</div>
              <div className="text-sm text-muted-500">{r.descripcion}</div>
              <div className="flex flex-wrap gap-2">
                <Button
                  size="sm"
                  onClick={() => descargar(r, "pdf")}
                  disabled={!gestionId || descargando === `${r.key}:pdf`}
                >
                  {descargando === `${r.key}:pdf` ? "Generando..." : "Descargar PDF"}
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => descargar(r, "excel")}
                  disabled={!gestionId || descargando === `${r.key}:excel`}
                >
                  {descargando === `${r.key}:excel` ? "Generando..." : "Descargar Excel"}
                </Button>
              </div>
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
