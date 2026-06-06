import { NavLink } from "react-router-dom";
import clsx from "clsx";
import type { RolCodigo } from "../../types";

/*
 * Sidebar
 * --------------------------------------------------------------------------
 * Menu lateral. El conjunto de items depende del rol del usuario.
 */
interface Item { to: string; label: string; }

const ITEMS_POR_ROL: Record<RolCodigo, Item[]> = {
  ADMINISTRADOR: [
    { to: "/admin",                  label: "Dashboard" },
    { to: "/admin/usuarios",         label: "Usuarios y roles" },
    { to: "/admin/gestiones",        label: "Gestion CUP" },
    { to: "/admin/grupos",           label: "Grupos" },
    { to: "/admin/postulantes",      label: "Postulantes" },
    { to: "/admin/asignaciones",     label: "Asignaciones docente" },
    { to: "/admin/notas-pendientes", label: "Notas pendientes" },
    { to: "/admin/calculo",          label: "Calculo de resultados" },
    { to: "/admin/resultados",       label: "Resultados" },
    { to: "/admin/reportes",         label: "Reportes" },
    { to: "/admin/bitacora",         label: "Bitacora" },
  ],
  ENCARGADO: [
    { to: "/encargado",                  label: "Dashboard" },
    { to: "/encargado/postulantes",      label: "Postulantes" },
    { to: "/encargado/grupos",           label: "Grupos" },
  ],
  DOCENTE: [
    { to: "/docente", label: "Mis asignaciones" },
  ],
  COORDINADOR: [
    { to: "/coordinador",                   label: "Dashboard" },
    { to: "/coordinador/asignaciones",      label: "Asignaciones docente" },
    { to: "/coordinador/notas-pendientes",  label: "Notas pendientes" },
    { to: "/coordinador/calculo",           label: "Calculo de resultados" },
    { to: "/coordinador/resultados",        label: "Resultados" },
    { to: "/coordinador/reportes",          label: "Reportes" },
  ],
};

interface SidebarProps {
  rolCodigo: RolCodigo;
  abierto: boolean;
  onClose: () => void;
}

export function Sidebar({ rolCodigo, abierto, onClose }: SidebarProps) {
  // "Mi cuenta" esta disponible para todos los roles.
  const items = [
    ...(ITEMS_POR_ROL[rolCodigo] ?? []),
    { to: "/mi-cuenta", label: "Mi cuenta" },
  ];

  // Contenido compartido por la version escritorio y el drawer movil.
  const contenido = (
    <>
      <div className="px-5 py-4 border-b border-institutional-700 flex items-center justify-between">
        <div>
          <div className="text-xs uppercase tracking-widest text-institutional-300">FICCT</div>
          <div className="font-semibold text-white">Sistema CUP</div>
        </div>
        {/* Boton cerrar: solo visible en el drawer movil. */}
        <button
          type="button"
          onClick={onClose}
          aria-label="Cerrar menu"
          className="md:hidden text-institutional-300 hover:text-white"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <path d="M6 6l12 12M18 6l-12 12" />
          </svg>
        </button>
      </div>
      <nav className="p-3 space-y-1 text-sm">
        {items.map((it) => (
          <NavLink
            key={it.to}
            to={it.to}
            onClick={onClose}
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
    </>
  );

  return (
    <>
      {/* Escritorio: sidebar fijo. */}
      <aside className="hidden md:block w-60 shrink-0 bg-institutional-800 text-institutional-100 min-h-screen sticky top-0">
        {contenido}
      </aside>

      {/* Movil: drawer deslizable con fondo oscuro. */}
      <div
        className={clsx(
          "md:hidden fixed inset-0 z-40 transition-opacity duration-200",
          abierto ? "opacity-100" : "pointer-events-none opacity-0",
        )}
      >
        <div className="absolute inset-0 bg-black/50" onClick={onClose} aria-hidden="true" />
        <aside
          className={clsx(
            "absolute left-0 top-0 h-full w-64 bg-institutional-800 text-institutional-100 shadow-xl transition-transform duration-200",
            abierto ? "translate-x-0" : "-translate-x-full",
          )}
        >
          {contenido}
        </aside>
      </div>
    </>
  );
}
