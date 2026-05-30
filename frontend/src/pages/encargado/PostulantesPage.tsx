import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { DataTable } from "../../components/tables/DataTable";
import { StatusBadge } from "../../components/tables/StatusBadge";
import { encargadoService } from "../../services/encargadoService";
import type { Paginated, Postulacion } from "../../types";

export function PostulantesPage() {
  const [data, setData] = useState<Paginated<Postulacion> | null>(null);
  const [q, setQ] = useState("");

  const cargar = (busq = q) => {
    encargadoService.postulaciones({ q: busq }).then(setData);
  };
  useEffect(() => { cargar(""); }, []);

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold text-institutional-800">Postulantes</h1>

      <Card>
        <div className="flex gap-2 items-end mb-4">
          <Input label="Buscar" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Documento, nombre, codigo" className="flex-1" />
          <Button variant="secondary" onClick={() => cargar()}>Buscar</Button>
        </div>

        <DataTable
          rows={data?.data ?? []}
          empty="Sin postulantes."
          columns={[
            { header: "Codigo",    cell: (p) => p.codigo_postulante ?? "-" },
            { header: "Nombre",    cell: (p) => p.persona ? `${p.persona.nombre} ${p.persona.apellido_paterno ?? ""}` : "-" },
            { header: "Documento", cell: (p) => p.persona ? `${p.persona.documento}${p.persona.expedido ? " "+p.persona.expedido : ""}` : "-" },
            { header: "Primera op",cell: (p) => p.carrera_primera?.nombre ?? "-" },
            { header: "Estado",    cell: (p) => <StatusBadge estado={p.estado} tipo="postulacion" /> },
            { header: "",          cell: (p) => <Link to={`/encargado/postulantes/${p.id}`} className="text-institutional-700 hover:underline text-sm">Ver</Link> },
          ]}
        />
      </Card>
    </div>
  );
}
