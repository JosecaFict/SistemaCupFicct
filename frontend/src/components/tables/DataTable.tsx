import type { ReactNode } from "react";

interface Column<T> {
  header: ReactNode;
  cell: (row: T) => ReactNode;
  className?: string;
}

export function DataTable<T extends { id: number | string }>({
  rows, columns, empty, footer,
}: {
  rows: T[];
  columns: Column<T>[];
  empty?: ReactNode;
  footer?: ReactNode;
}) {
  if (rows.length === 0) {
    return <div className="text-sm text-muted-500 py-6 text-center">{empty ?? "Sin registros."}</div>;
  }
  return (
    <>
      {/* Escritorio: tabla clasica con scroll horizontal de respaldo. */}
      <div className="hidden lg:block overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="bg-muted-50 text-muted-600 text-left">
              {columns.map((c, i) => (
                <th key={i} className={`px-4 py-2 font-medium ${c.className ?? ""}`}>{c.header}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-t border-muted-100 hover:bg-muted-50/50">
                {columns.map((c, i) => (
                  <td key={i} className={`px-4 py-2 ${c.className ?? ""}`}>{c.cell(row)}</td>
                ))}
              </tr>
            ))}
          </tbody>
          {footer && <tfoot>{footer}</tfoot>}
        </table>
      </div>

      {/* Movil y celular en horizontal: cada fila se muestra como tarjeta etiqueta/valor. */}
      <div className="lg:hidden space-y-3">
        {rows.map((row) => (
          <div key={row.id} className="rounded-lg border border-muted-100 bg-white p-3 space-y-2">
            {columns.map((c, i) => {
              const valor = c.cell(row);
              // Columnas sin encabezado (ej. boton "Ver") ocupan toda la fila.
              if (!c.header) {
                return <div key={i} className="pt-1">{valor}</div>;
              }
              return (
                <div key={i} className="flex justify-between gap-3 text-sm">
                  <span className="text-muted-500 shrink-0">{c.header}</span>
                  <span className="text-right text-institutional-800 break-words">{valor}</span>
                </div>
              );
            })}
          </div>
        ))}
      </div>
    </>
  );
}
