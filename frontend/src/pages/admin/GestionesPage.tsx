import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Badge } from "../../components/ui/Badge";
import { DataTable } from "../../components/tables/DataTable";
import { adminService } from "../../services/adminService";
import type { GestionCup, Paginated } from "../../types";

const fmtFecha = (iso?: string | null) =>
  iso ? iso.slice(0, 10).split("-").reverse().join("/") : "-";

export function GestionesPage() {
  const [data, setData] = useState<Paginated<GestionCup> | null>(null);

  useEffect(() => {
    adminService.gestiones().then(setData);
  }, []);

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-institutional-800">Gestiones CUP</h1>
        <Link to="/admin/gestiones/nueva"><Button>Nueva gestion</Button></Link>
      </div>

      <Card>
        <DataTable
          rows={data?.data ?? []}
          empty="Sin gestiones registradas."
          columns={[
            { header: "Codigo",       cell: (g) => <Link to={`/admin/gestiones/${g.id}`} className="text-institutional-700 hover:underline">{g.codigo}</Link> },
            { header: "Nombre",       cell: (g) => g.nombre },
            { header: "Preinscr.",    cell: (g) => `${fmtFecha(g.fecha_inicio_preinscripcion)} a ${fmtFecha(g.fecha_cierre_preinscripcion)}` },
            { header: "Capacidad/G",  cell: (g) => g.capacidad_maxima_grupo },
            { header: "Estimado",     cell: (g) => g.estimado_postulantes },
            { header: "Turnos",       cell: (g) => g.turnos_habilitados },
            { header: "Estado",       cell: (g) => (
                <Badge tone={g.estado === "ACTIVA" ? "success" : g.estado === "CERRADA" ? "neutral" : "info"}>
                  {g.estado}
                </Badge>
              ) },
            { header: "Acciones",     cell: (g) => (
                <Link to={`/admin/gestiones/${g.id}`}>
                  <Button variant="secondary" size="sm">Editar</Button>
                </Link>
              ) },
          ]}
        />
      </Card>
    </div>
  );
}
