import { NavLink } from "react-router-dom";
import clsx from "clsx";
import type { RolCodigo } from "../../types";

/*
 * Sidebar
 * --------------------------------------------------------------------------
 * Menu lateral. El conjunto de items depende del rol del usuario.
 * Items de Docente y Coordinador estan PREPARADOS para Ciclo 2.
 */
interface Item { to: string; label: string; }

const ITEMS_POR_ROL: Record<RolCodigo, Item[]> = {
  ADMINISTRADOR: [
    { to: "/admin",            label: "Dashboard" },
    { to: "/admin/usuarios",   label: "Usuarios y roles" },
    { to: "/admin/gestiones",  label: "Gestion CUP" },
    { to: "/admin/grupos",     label: "Grupos" },
    { to: "/admin/postulantes",label: "Postulantes" },
    { to: "/admin/bitacora",   label: "Bitacora" },
  ],
  ENCARGADO: [
    { to: "/encargado",                  label: "Dashboard" },
    { to: "/encargado/postulantes",      label: "Postulantes" },
    { to: "/encargado/grupos",           label: "Grupos" },
  ],
  DOCENTE: [
    { to: "/docente", label: "Dashboard (Ciclo 2)" },
  ],
  COORDINADOR: [
    { to: "/coordinador", label: "Dashboard (Ciclo 2)" },
  ],
};

export function Sidebar({ rolCodigo }: { rolCodigo: RolCodigo }) {
  const items = ITEMS_POR_ROL[rolCodigo] ?? [];

  return (
    <aside className="w-60 shrink-0 bg-institutional-800 text-institutional-100 min-h-screen sticky top-0">
      <div className="px-5 py-4 border-b border-institutional-700">
        <div className="text-xs uppercase tracking-widest text-institutional-300">FICCT</div>
        <div className="font-semibold text-white">Sistema CUP</div>
      </div>
      <nav className="p-3 space-y-1 text-sm">
        {items.map((it) => (
          <NavLink
            key={it.to}
            to={it.to}
            end={it.to.endsWith("/admin") || it.to.endsWith("/encargado") || it.to.endsWith("/docente") || it.to.endsWith("/coordinador")}
            className={({ isActive }) =>
              clsx(
                "block rounded-md px-3 py-2 hover:bg-institutional-700",
                isActive && "bg-institutional-700 text-white",
              )
            }
          >
            {it.label}
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}
