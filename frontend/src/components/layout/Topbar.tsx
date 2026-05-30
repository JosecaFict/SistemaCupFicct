import { useNavigate } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { Button } from "../ui/Button";

/*
 * Topbar -- barra superior del area autenticada con nombre y boton logout.
 */
export function Topbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate("/login", { replace: true });
  };

  return (
    <header className="bg-white border-b border-muted-100 sticky top-0 z-10">
      <div className="px-6 py-3 flex items-center justify-between">
        <div className="text-sm text-muted-500">
          Hola, <span className="text-institutional-800 font-medium">{user?.nombre}</span>
          {user?.rol && <span className="ml-2 text-xs text-muted-400">({user.rol.nombre})</span>}
        </div>
        <Button variant="ghost" size="sm" onClick={handleLogout}>Cerrar sesion</Button>
      </div>
    </header>
  );
}
