import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { Select } from "../../components/ui/Select";
import { Badge } from "../../components/ui/Badge";
import type { BadgeTone } from "../../components/ui/Badge";
import { Alert } from "../../components/ui/Alert";
import { DataTable } from "../../components/tables/DataTable";
import { encargadoService } from "../../services/encargadoService";
import { useAuth } from "../../context/AuthContext";
import type { GestionCup, Paginated, Postulacion } from "../../types";

/*
 * Estado mostrado al encargado: solo importa el avance de la INSCRIPCION,
 * no el resultado de examen. Por eso se simplifica a Pendiente/Inscrito/Anulado.
 */
function estadoEncargado(estado: string): { label: string; tone: BadgeTone } {
  if (estado === "ANULADO") return { label: "Anulado", tone: "neutral" };
  if (["INSCRITO", "ACEPTADO", "REPROBADO", "SIN_CUPO"].includes(estado)) {
    return { label: "Inscrito", tone: "success" };
  }
  return { label: "Pendiente", tone: "warning" };
}

export function PostulantesPage() {
  const { user } = useAuth();
  // El encargado es un rol acotado (rota, solo inscribe y mira cupos): se le
  // limita a la gestion ACTIVA. El ADMINISTRADOR conserva la vista completa
  // (todas las gestiones + filtros) porque tiene rol de supervision.
  const esAdmin = user?.rol?.codigo === "ADMINISTRADOR";

  const [data, setData] = useState<Paginated<Postulacion> | null>(null);
  const [gestiones, setGestiones] = useState<GestionCup[]>([]);
  const [gestionId, setGestionId] = useState<string>("");
  const [gestionEstado, setGestionEstado] = useState<string>("");
  const [q, setQ] = useState("");
  const [cargandoGestiones, setCargandoGestiones] = useState(true);

  // Gestion ACTIVA (solo relevante para el encargado).
  const gestionActiva = gestiones.find((g) => g.estado === "ACTIVA") ?? null;

  const cargar = (busq = q, gid = gestionId, gest = gestionEstado) => {
    const params: Record<string, string | number> = {};
    if (busq) params.q = busq;

    if (esAdmin) {
      if (gid)  params.gestion_cup_id = Number(gid);
      if (gest) params.gestion_estado = gest;
      encargadoService.postulaciones(params).then(setData);
    } else {
      // Encargado: bloqueado a la gestion activa. Sin activa no se lista nada.
      if (!gestionActiva) { setData(null); return; }
      params.gestion_cup_id = gestionActiva.id;
      encargadoService.postulaciones(params).then(setData);
    }
  };

  useEffect(() => {
    encargadoService.gestiones()
      .then(setGestiones)
      .catch(() => {})
      .finally(() => setCargandoGestiones(false));
  }, []);

  // Recarga: admin al cambiar filtros; encargado cuando ya se conoce la activa.
  useEffect(() => {
    if (esAdmin) cargar(q, gestionId, gestionEstado);
    else if (!cargandoGestiones) cargar(q);
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [gestionId, gestionEstado, esAdmin, cargandoGestiones, gestionActiva?.id]);

  const sinGestionActiva = !esAdmin && !cargandoGestiones && !gestionActiva;

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold text-institutional-800">Postulantes</h1>

      {sinGestionActiva ? (
        <Alert tone="warning" title="Sin gestion activa">
          No hay una gestion CUP activa en este momento. Cuando se abra una, aqui apareceran sus postulantes para inscribir.
        </Alert>
      ) : (
        <Card>
          <div className="flex flex-wrap gap-2 items-end mb-4">
            {esAdmin ? (
              <>
                <div className="w-full sm:w-52">
                  <Select label="Filtrar por gestion" value={gestionId} onChange={(e) => setGestionId(e.target.value)}>
                    <option value="">Todas las gestiones</option>
                    {gestiones.map((g) => (
                      <option key={g.id} value={g.id}>{g.codigo}</option>
                    ))}
                  </Select>
                </div>
                <div className="w-full sm:w-44">
                  <Select label="Filtrar por estado" value={gestionEstado} onChange={(e) => setGestionEstado(e.target.value)}>
                    <option value="">Todos</option>
                    <option value="ACTIVA">Activa</option>
                    <option value="CERRADA">Cerrada</option>
                  </Select>
                </div>
              </>
            ) : (
              gestionActiva && (
                <div className="w-full sm:w-auto">
                  <div className="text-xs text-muted-500 mb-1">Gestion activa</div>
                  <Badge tone="success">{gestionActiva.codigo}</Badge>
                </div>
              )
            )}
            <Input label="Buscar" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Documento o nombre" className="flex-1 min-w-[180px]" />
            <Button variant="secondary" onClick={() => cargar()}>Buscar</Button>
          </div>

          <DataTable
            rows={data?.data ?? []}
            empty="Sin postulantes."
            columns={[
              { header: "Nombre",    cell: (p) => p.persona ? `${p.persona.nombre} ${p.persona.apellido_paterno ?? ""}` : "-" },
              { header: "Documento", cell: (p) => p.persona ? `${p.persona.documento}${p.persona.expedido ? " "+p.persona.expedido : ""}` : "-" },
              { header: "Primera op",cell: (p) => p.carrera_primera?.nombre ?? "-" },
              { header: "Estado",    cell: (p) => { const e = estadoEncargado(p.estado); return <Badge tone={e.tone}>{e.label}</Badge>; } },
              { header: "",          cell: (p) => <Link to={`/encargado/postulantes/${p.id}`} className="text-institutional-700 hover:underline text-sm">Ver</Link> },
            ]}
          />
        </Card>
      )}
    </div>
  );
}
