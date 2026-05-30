import clsx from "clsx";
import type { ButtonHTMLAttributes } from "react";

type Variant = "primary" | "secondary" | "ghost" | "success" | "danger";
type Size = "sm" | "md";

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  loading?: boolean;
}

export function Button({ variant = "primary", size = "md", loading, className, children, disabled, ...rest }: Props) {
  return (
    <button
      {...rest}
      disabled={disabled || loading}
      className={clsx(
        "inline-flex items-center justify-center font-medium rounded-md transition focus:outline-none focus:ring-2 focus:ring-offset-1",
        size === "md" && "px-4 py-2 text-sm",
        size === "sm" && "px-3 py-1.5 text-xs",
        variant === "primary"   && "bg-institutional-700 hover:bg-institutional-800 text-white focus:ring-institutional-400",
        variant === "secondary" && "bg-white text-institutional-700 border border-institutional-200 hover:bg-institutional-50",
        variant === "ghost"     && "bg-transparent text-institutional-700 hover:bg-institutional-50",
        variant === "success"   && "bg-success-600 hover:bg-success-700 text-white focus:ring-success-300",
        variant === "danger"    && "bg-danger-600 hover:bg-danger-500 text-white",
        (disabled || loading) && "opacity-60 cursor-not-allowed",
        className,
      )}
    >
      {loading && (
        <svg className="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none">
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 100 16v-2a6 6 0 01-6-6z" />
        </svg>
      )}
      {children}
    </button>
  );
}
