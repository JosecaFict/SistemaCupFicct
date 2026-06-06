import { useState } from "react";
import { Outlet } from "react-router-dom";
import { Sidebar } from "./Sidebar";
import { Topbar } from "./Topbar";
import { useAuth } from "../../context/AuthContext";

/*
 * AppLayout -- layout para usuarios autenticados.
 * Combina Sidebar + Topbar + zona de contenido. El sidebar cambia de items
 * segun el rol del usuario logueado.
 *
 * Responsive: en escritorio el sidebar es fijo; en movil se oculta y se abre
 * como drawer desde el boton hamburguesa de la Topbar.
 */
export function AppLayout() {
  const { user } = useAuth();
  const [menuAbierto, setMenuAbierto] = useState(false);
  if (!user?.rol) return null;

  return (
    <div className="min-h-screen flex bg-muted-50">
      <Sidebar
        rolCodigo={user.rol.codigo}
        abierto={menuAbierto}
        onClose={() => setMenuAbierto(false)}
      />
      {/* min-w-0 evita que las tablas anchas empujen el layout en movil */}
      <div className="flex-1 flex flex-col min-w-0">
        <Topbar onAbrirMenu={() => setMenuAbierto(true)} />
        <main className="p-4 md:p-6 flex-1">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
