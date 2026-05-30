import type { ReactNode } from "react";

// Bloque visual para agrupar campos por seccion en un formulario largo.
export function FormSection({ title, description, children }: {
  title: string;
  description?: string;
  children: ReactNode;
}) {
  return (
    <section className="border border-muted-100 rounded-lg bg-white">
      <header className="px-5 py-3 border-b border-muted-100">
        <h3 className="text-sm font-semibold text-institutional-800">{title}</h3>
        {description && <p className="text-xs text-muted-500">{description}</p>}
      </header>
      <div className="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">{children}</div>
    </section>
  );
}
