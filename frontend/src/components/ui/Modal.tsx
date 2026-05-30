import type { ReactNode } from "react";

export function Modal({ open, onClose, title, children, footer }: {
  open: boolean;
  onClose: () => void;
  title?: ReactNode;
  children?: ReactNode;
  footer?: ReactNode;
}) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4">
      <div className="bg-white rounded-lg shadow-card w-full max-w-lg">
        <div className="px-5 py-3 border-b border-muted-100 flex items-center justify-between">
          <div className="font-semibold text-institutional-800">{title}</div>
          <button onClick={onClose} className="text-muted-500 hover:text-institutional-700">x</button>
        </div>
        <div className="p-5">{children}</div>
        {footer && <div className="px-5 py-3 border-t border-muted-100 flex justify-end gap-2">{footer}</div>}
      </div>
    </div>
  );
}
