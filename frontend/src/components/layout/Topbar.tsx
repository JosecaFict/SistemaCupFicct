import { useNavigate } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { Button } from "../ui/Button";

/*
 * Topbar -- barra superior del area autenticada con nombre y boton logout.
 * En movil incluye el boton hamburguesa que abre el menu lateral (drawer).
 */
export function Topbar({ onAbrirMenu }: { onAbrirMenu: () => void }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate("/login", { replace: true });
  };

  return (
    <header className="bg-white border-b border-muted-100 sticky top-0 z-10">
      <div className="px-4 md:px-6 py-3 flex items-center justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          {/* Hamburguesa: solo en movil. */}
          <button
            type="button"
            onClick={onAbrirMenu}
            aria-label="Abrir menu"
            className="lg:hidden -ml-1 p-1 text-institutional-700 hover:text-institutional-900"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
              <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div className="text-sm text-muted-500 truncate">
            Hola, <span className="text-institutional-800 font-medium">{user?.nombre}</span>
            {user?.rol && <span className="ml-2 text-xs text-muted-400 hidden sm:inline">({user.rol.nombre})</span>}
          </div>
        </div>
        <Button variant="ghost" size="sm" onClick={handleLogout}>Cerrar sesion</Button>
      </div>
    </header>
  );
}
