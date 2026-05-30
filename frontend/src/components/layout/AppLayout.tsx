import { Outlet } from "react-router-dom";
import { Sidebar } from "./Sidebar";
import { Topbar } from "./Topbar";
import { useAuth } from "../../context/AuthContext";

/*
 * AppLayout -- layout para usuarios autenticados.
 * Combina Sidebar + Topbar + zona de contenido. El sidebar cambia de items
 * segun el rol del usuario logueado.
 */
export function AppLayout() {
  const { user } = useAuth();
  if (!user?.rol) return null;

  return (
    <div className="min-h-screen flex bg-muted-50">
      <Sidebar rolCodigo={user.rol.codigo} />
      <div className="flex-1 flex flex-col">
        <Topbar />
        <main className="p-6 flex-1">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
