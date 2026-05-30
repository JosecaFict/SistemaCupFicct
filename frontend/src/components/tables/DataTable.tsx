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
    <div className="overflow-x-auto">
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
  );
}
