import clsx from "clsx";
import type { ReactNode } from "react";

export type BadgeTone = "neutral" | "info" | "success" | "warning" | "danger";

export function Badge({ children, tone = "neutral", className }: {
  children: ReactNode;
  tone?: BadgeTone;
  className?: string;
}) {
  return (
    <span
      className={clsx(
        "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium",
        tone === "neutral" && "bg-muted-100 text-muted-700",
        tone === "info"    && "bg-institutional-50 text-institutional-700 border border-institutional-100",
        tone === "success" && "bg-success-50 text-success-700 border border-success-100",
        tone === "warning" && "bg-orange-50 text-warning-600 border border-orange-100",
        tone === "danger"  && "bg-red-50 text-danger-600 border border-red-100",
        className,
      )}
    >
      {children}
    </span>
  );
}
