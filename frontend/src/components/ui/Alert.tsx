import clsx from "clsx";
import type { ReactNode } from "react";

export function Alert({ tone = "info", title, children }: {
  tone?: "info" | "success" | "warning" | "danger";
  title?: string;
  children?: ReactNode;
}) {
  return (
    <div
      className={clsx(
        "rounded-md border px-4 py-3 text-sm",
        tone === "info"    && "bg-institutional-50 border-institutional-200 text-institutional-800",
        tone === "success" && "bg-success-50 border-success-200 text-success-700",
        tone === "warning" && "bg-orange-50 border-orange-200 text-warning-600",
        tone === "danger"  && "bg-red-50 border-red-200 text-danger-600",
      )}
    >
      {title && <div className="font-semibold mb-1">{title}</div>}
      {children}
    </div>
  );
}
