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
  user?: { nombre: string; apellidos: string } | null;
  datos?: Record<string, unknown> | null;
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
            { header: "Usuario", cell: (e) => e.user ? `${e.user.nombre} ${e.user.apellidos}` : "-" },
            { header: "Entidad", cell: (e) => e.entidad ? `${e.entidad} #${e.entidad_id ?? ""}` : "-" },
            { header: "IP",      cell: (e) => e.ip ?? "-" },
          ]}
        />
      </Card>
    </div>
  );
}
