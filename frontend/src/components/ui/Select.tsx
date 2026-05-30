import clsx from "clsx";
import { forwardRef } from "react";
import type { SelectHTMLAttributes } from "react";

interface Props extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
}

export const Select = forwardRef<HTMLSelectElement, Props>(function Select(
  { label, error, className, id, children, ...rest }, ref
) {
  const selectId = id ?? rest.name;
  return (
    <div className="flex flex-col gap-1">
      {label && (
        <label htmlFor={selectId} className="text-sm font-medium text-muted-700">{label}</label>
      )}
      <select
        ref={ref}
        id={selectId}
        {...rest}
        className={clsx(
          "rounded-md border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2",
          error
            ? "border-danger-500 focus:ring-danger-500/30"
            : "border-muted-200 focus:ring-institutional-300 focus:border-institutional-500",
          className,
        )}
      >
        {children}
      </select>
      {error && <span className="text-xs text-danger-600">{error}</span>}
    </div>
  );
});
