import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { DataTable } from "../../components/tables/DataTable";
import { Badge } from "../../components/ui/Badge";
import { Select } from "../../components/ui/Select";
import { encargadoService } from "../../services/encargadoService";
import { publicService } from "../../services/publicService";
import type { GestionCup, Grupo, Turno } from "../../types";

/*
 * GruposPage -- listado de grupos generados para la gestion activa.
 * Muestra capacidad / inscritos / cupos libres por grupo.
 */
export function GruposPage() {
  const [grupos, setGrupos] = useState<Grupo[]>([]);
  const [turnos, setTurnos] = useState<Turno[]>([]);
  const [gestiones, setGestiones] = useState<GestionCup[]>([]);
  const [turnoId, setTurnoId] = useState<string>("");
  const [gestionId, setGestionId] = useState<string>("");

  useEffect(() => {
    publicService.turnos().then(setTurnos);
    encargadoService.gestiones().then(setGestiones).catch(() => {});
  }, []);

  useEffect(() => {
    encargadoService.grupos({
      gestion_cup_id: gestionId ? Number(gestionId) : undefined,
      turno_id:       turnoId   ? Number(turnoId)   : undefined,
    }).then(setGrupos);
  }, [gestionId, turnoId]);

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold text-institutional-800">Grupos</h1>

      <Card>
        <div className="flex flex-wrap items-end gap-3">
          <div className="w-full sm:w-56">
            <Select label="Filtrar por gestion" value={gestionId} onChange={(e) => setGestionId(e.target.value)}>
              <option value="">Todas las gestiones</option>
              {gestiones.map((g) => (
                <option key={g.id} value={g.id}>{g.codigo}</option>
              ))}
            </Select>
          </div>
          <div className="w-full sm:w-56">
            <Select label="Filtrar por turno" value={turnoId} onChange={(e) => setTurnoId(e.target.value)}>
              <option value="">Todos los turnos</option>
              {turnos.map((t) => (
                <option key={t.id} value={t.id}>{t.nombre}</option>
              ))}
            </Select>
          </div>
        </div>
      </Card>

      <Card>
        <DataTable
          rows={grupos}
          empty="No hay grupos generados. Crea/edita la gestion CUP y pulsa 'Generar grupos automaticamente'."
          columns={[
            { header: "Codigo",   cell: (g) => <b>{g.codigo}</b> },
            { header: "Gestion",  cell: (g) => g.gestion?.codigo ?? "-" },
            { header: "Turno",    cell: (g) => g.turno?.nombre ?? "-" },
            { header: "Capacidad",cell: (g) => g.capacidad },
            { header: "Inscritos",cell: (g) => g.inscritos_actuales },
            { header: "Libres",   cell: (g) => g.capacidad - g.inscritos_actuales },
            { header: "Estado",   cell: (g) => {
              // Si la gestion esta CERRADA, el grupo no esta operativo aunque su
              // propio campo 'estado' siga en ACTIVO: reflejamos el estado real.
              const efectivo = g.gestion?.estado === "CERRADA" ? "CERRADO" : g.estado;
              return <Badge tone={efectivo === "ACTIVO" ? "success" : "neutral"}>{efectivo}</Badge>;
            } },
          ]}
          footer={
            <tr className="border-t-2 border-muted-200 bg-muted-50 font-semibold text-institutional-800">
              <td className="px-4 py-2 text-right" colSpan={3}>Totales ({grupos.length} grupos):</td>
              <td className="px-4 py-2">{grupos.reduce((s, g) => s + g.capacidad, 0)}</td>
              <td className="px-4 py-2">{grupos.reduce((s, g) => s + g.inscritos_actuales, 0)}</td>
              <td className="px-4 py-2">{grupos.reduce((s, g) => s + (g.capacidad - g.inscritos_actuales), 0)}</td>
              <td></td>
            </tr>
          }
        />
      </Card>
    </div>
  );
}
