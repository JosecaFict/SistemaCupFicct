import clsx from "clsx";
import type { ReactNode } from "react";

export function Card({ children, className, title, action }: {
  children: ReactNode;
  className?: string;
  title?: ReactNode;
  action?: ReactNode;
}) {
  return (
    <div className={clsx("bg-white border border-muted-100 rounded-lg shadow-card overflow-hidden", className)}>
      {(title || action) && (
        <div className="px-5 py-3 border-b border-muted-100 flex items-center justify-between gap-3">
          <div className="text-sm font-semibold text-institutional-800">{title}</div>
          <div>{action}</div>
        </div>
      )}
      <div className="p-5">{children}</div>
    </div>
  );
}
