import type { ComprobantePago } from "../../types";

/*
 * ComprobantePreview
 * --------------------------------------------------------------------------
 * Comprobante de pago de inscripcion (CU7). Listo para imprimir con
 * window.print() / "Guardar como PDF" del navegador, sin libs en el backend
 * (mismo enfoque que BoletaPreview).
 */
export function ComprobantePreview({ data }: { data: ComprobantePago }) {
  const fecha = data.fecha_aprobacion
    ? new Date(data.fecha_aprobacion).toLocaleString()
    : "—";
  const via = data.modo === "simulated" ? "Simulado" : "Stripe Checkout";

  return (
    <div className="bg-white border border-muted-200 rounded-lg p-8 max-w-2xl mx-auto print-page">
      <div className="flex items-center justify-between border-b border-muted-200 pb-4 mb-6">
        <div>
          <div className="text-xs uppercase tracking-wide text-muted-500">UAGRM · FICCT</div>
          <div className="text-xl font-bold text-institutional-800">Comprobante de pago</div>
          <div className="text-xs text-muted-500">Sistema CUP — Curso Preuniversitario</div>
        </div>
        <div className="text-right">
          <div className="text-xs text-muted-500">Estado</div>
          <div className="text-lg font-bold text-emerald-600">
            {data.estado === "APROBADO" ? "✓ APROBADO" : data.estado}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <Item label="Referencia" value={data.referencia ?? "—"} mono />
        <Item label="Fecha de aprobacion" value={fecha} />
        <Item label="Postulante" value={data.postulante} />
        <Item label="Gestion" value={data.gestion} />
        <Item label="Concepto" value={data.concepto} />
        <Item label="Pago via" value={via} />
      </div>

      <div className="mt-6 rounded-md border border-institutional-200 bg-institutional-50 px-5 py-4 flex items-center justify-between">
        <div className="text-[11px] uppercase tracking-wide text-muted-500">Monto pagado</div>
        <div className="text-2xl font-bold text-institutional-800">
          {data.monto} {data.moneda}
        </div>
      </div>

      <div className="mt-6 border-t border-muted-200 pt-4 text-xs text-muted-500">
        Conserve este comprobante. Preséntelo al encargado de inscripción junto
        con sus documentos para completar el proceso.
      </div>
    </div>
  );
}

function Item({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <div>
      <div className="text-[11px] uppercase tracking-wide text-muted-500">{label}</div>
      <div className={`font-medium text-institutional-800 ${mono ? "font-mono text-xs" : ""}`}>{value}</div>
    </div>
  );
}
