import type { ReactNode } from "react";

// Tarjeta para metricas del dashboard.
export function StatCard({ label, value, helper, accent = "institutional" }: {
  label: string;
  value: ReactNode;
  helper?: string;
  accent?: "institutional" | "success" | "warning" | "danger";
}) {
  const accentClass =
    accent === "success"  ? "text-success-600" :
    accent === "warning"  ? "text-warning-600" :
    accent === "danger"   ? "text-danger-600"  :
    "text-institutional-700";

  return (
    <div className="bg-white border border-muted-100 rounded-lg shadow-card p-5">
      <div className="text-xs uppercase tracking-wide text-muted-500">{label}</div>
      <div className={`text-3xl font-semibold mt-1 ${accentClass}`}>{value}</div>
      {helper && <div className="text-xs text-muted-500 mt-1">{helper}</div>}
    </div>
  );
}
