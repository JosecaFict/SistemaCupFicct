import type { BoletaPayload } from "../../types";

/*
 * BoletaPreview
 * --------------------------------------------------------------------------
 * Renderizado de la boleta de inscripcion (CU10). Listo para imprimir con
 * window.print(). En Ciclo 6 se reemplaza por PDF nativo.
 */
export function BoletaPreview({ data }: { data: BoletaPayload }) {
  return (
    <div className="bg-white border border-muted-200 rounded-lg p-8 max-w-3xl mx-auto print-page">
      <div className="flex items-center justify-between border-b border-muted-200 pb-4 mb-6">
        <div>
          <div className="text-xs uppercase tracking-wide text-muted-500">Sistema CUP FICCT</div>
          <div className="text-xl font-bold text-institutional-800">Boleta de inscripcion</div>
        </div>
        <div className="text-right">
          <div className="text-xs text-muted-500">Codigo de postulante</div>
          <div className="text-2xl font-mono font-bold text-institutional-700">{data.codigo_postulante}</div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4 text-sm">
        <Item label="Nombre completo" value={data.nombre_completo} />
        <Item label="Documento" value={`${data.documento.numero}${data.documento.expedido ? " " + data.documento.expedido : ""} (${data.documento.tipo})`} />
        <Item label="Gestion" value={`${data.gestion.codigo} - ${data.gestion.nombre}`} />
        <Item label="Turno" value={`${data.turno.nombre} (${data.turno.codigo})`} />
        <Item label="Grupo" value={data.grupo.codigo} />
        <Item label="Modalidad" value={data.modalidad ?? "Por asignar"} />
        <Item label="Aula / Enlace" value={data.aula_o_enlace ?? "Por asignar"} />
        <Item label="Primera opcion" value={data.carrera_primera} />
        {data.carrera_segunda && <Item label="Segunda opcion" value={data.carrera_segunda} />}
      </div>

      <div className="mt-4">
        <div className="text-[11px] uppercase tracking-wide text-muted-500">Horario</div>
        {data.horario && data.horario.length > 0 ? (
          <div className="font-medium text-muted-700 leading-snug">
            {data.horario.map((h, i) => (
              <div key={i}>{h.materia}|{h.dias}|{h.hora}|{h.aula}</div>
            ))}
          </div>
        ) : (
          <div className="font-medium italic text-muted-700">Horario por asignar</div>
        )}
      </div>

      <div className="mt-8 text-xs text-muted-500 border-t border-muted-200 pt-3 flex justify-between">
        <span>Fecha de inscripcion: {data.fecha_inscripcion ? new Date(data.fecha_inscripcion).toLocaleString() : "-"}</span>
        <span>Confirmada por: {data.confirmada_por ?? "-"}</span>
      </div>
    </div>
  );
}

function Item({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[11px] uppercase tracking-wide text-muted-500">{label}</div>
      <div className="font-medium text-muted-700">{value}</div>
    </div>
  );
}
