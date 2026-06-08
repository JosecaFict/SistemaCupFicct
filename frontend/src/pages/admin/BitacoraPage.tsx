import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { DataTable } from "../../components/tables/DataTable";
import { adminService } from "../../services/adminService";

interface Entry {
  id: number;
  evento: string;
  entidad: string | null;
  entidad_id: number | null;
  ip: string | null;
  created_at: string;
  user?: { nombre: string; apellidos: string; email: string } | null;
  datos?: Record<string, unknown> | null;
}

/* Etiqueta legible por tipo de entidad (en vez de "user #1"). */
const ENTIDAD_LABEL: Record<string, string> = {
  user: "Usuario",
  postulacion: "Postulacion",
  postulacion_requisito: "Requisito",
  pago: "Pago",
  inscripcion: "Inscripcion",
  gestion_cup: "Gestion",
  asignacion_docente: "Asignacion docente",
  bloque_notas: "Bloque de notas",
  postulacion_requisitos: "Requisitos",
};

function etiquetaEntidad(tipo: string | null): string {
  if (!tipo) return "-";
  return ENTIDAD_LABEL[tipo] ?? tipo;
}

export function BitacoraPage() {
  const [rows, setRows] = useState<Entry[]>([]);

  useEffect(() => {
    adminService.bitacora().then((r) => setRows((r as { data: Entry[] }).data ?? []));
  }, []);

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold text-institutional-800">Bitacora</h1>

      <Card>
        <DataTable
          rows={rows}
          empty="Sin eventos."
          columns={[
            { header: "Fecha",   cell: (e) => new Date(e.created_at).toLocaleString() },
            { header: "Evento",  cell: (e) => <b>{e.evento}</b> },
            { header: "Usuario", cell: (e) => e.user
                ? <span>{e.user.email}<span className="text-muted-400"> · {e.user.nombre} {e.user.apellidos}</span></span>
                : <span className="text-muted-400">Sistema</span> },
            { header: "Entidad", cell: (e) => etiquetaEntidad(e.entidad) },
            { header: "IP",      cell: (e) => e.ip ?? "-" },
          ]}
        />
      </Card>
    </div>
  );
}
