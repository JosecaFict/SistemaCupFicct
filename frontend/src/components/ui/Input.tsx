import clsx from "clsx";
import { forwardRef } from "react";
import type { InputHTMLAttributes } from "react";

interface Props extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  hint?: string;
}

export const Input = forwardRef<HTMLInputElement, Props>(function Input(
  { label, error, hint, className, id, ...rest }, ref
) {
  const inputId = id ?? rest.name;
  return (
    <div className="flex flex-col gap-1">
      {label && (
        <label htmlFor={inputId} className="text-sm font-medium text-muted-700">
          {label}
        </label>
      )}
      <input
        ref={ref}
        id={inputId}
        {...rest}
        className={clsx(
          "rounded-md border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2",
          error
            ? "border-danger-500 focus:ring-danger-500/30"
            : "border-muted-200 focus:ring-institutional-300 focus:border-institutional-500",
          className,
        )}
      />
      {error ? (
        <span className="text-xs text-danger-600">{error}</span>
      ) : hint ? (
        <span className="text-xs text-muted-500">{hint}</span>
      ) : null}
    </div>
  );
});
