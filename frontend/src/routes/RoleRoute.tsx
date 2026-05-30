import { Navigate } from "react-router-dom";
import type { ReactNode } from "react";
import { useAuth } from "../context/AuthContext";
import type { RolCodigo } from "../types";

/*
 * RoleRoute -- exige que el usuario tenga uno de los roles permitidos.
 * Si no, redirige al inicio del rol que SI tiene (o login si no esta logueado).
 */
export function RoleRoute({ roles, children }: { roles: RolCodigo[]; children: ReactNode }) {
  const { user, loading } = useAuth();

  if (loading) return null;
  if (!user)   return <Navigate to="/login" replace />;
  if (!user.rol) return <Navigate to="/login" replace />;

  if (!roles.includes(user.rol.codigo)) {
    return <Navigate to="/sin-acceso" replace />;
  }
  return <>{children}</>;
}
