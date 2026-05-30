import { createContext, useCallback, useContext, useMemo, useState } from "react";
import type { ReactNode } from "react";
import clsx from "clsx";

// Toast minimalista para feedback al usuario (sin libreria externa).
type ToastTone = "success" | "danger" | "warning" | "info";
interface Toast { id: number; message: string; tone: ToastTone; }
interface ToastCtx { push: (msg: string, tone?: ToastTone) => void; }

const Ctx = createContext<ToastCtx | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Toast[]>([]);

  const push = useCallback((message: string, tone: ToastTone = "info") => {
    const id = Date.now() + Math.random();
    setItems((s) => [...s, { id, message, tone }]);
    setTimeout(() => setItems((s) => s.filter((t) => t.id !== id)), 4500);
  }, []);

  const value = useMemo(() => ({ push }), [push]);
  return (
    <Ctx.Provider value={value}>
      {children}
      <div className="fixed top-4 right-4 z-50 flex flex-col gap-2">
        {items.map((t) => (
          <div key={t.id}
            className={clsx(
              "rounded-md px-4 py-2 shadow-card text-sm text-white min-w-[240px]",
              t.tone === "success" && "bg-success-600",
              t.tone === "danger"  && "bg-danger-600",
              t.tone === "warning" && "bg-warning-600",
              t.tone === "info"    && "bg-institutional-700",
            )}
          >{t.message}</div>
        ))}
      </div>
    </Ctx.Provider>
  );
}

export function useToast(): ToastCtx {
  const c = useContext(Ctx);
  if (!c) throw new Error("useToast debe usarse dentro de <ToastProvider>");
  return c;
}
