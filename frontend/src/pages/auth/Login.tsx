import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import type { Location } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { Input } from "../../components/ui/Input";
import { PasswordInput } from "../../components/ui/PasswordInput";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { INICIO_POR_ROL } from "../../data/constants";

/*
 * Login (CU1)
 * --------------------------------------------------------------------------
 * Despues del login redirige al panel correspondiente al rol del usuario.
 */
export function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation() as Location & { state?: { from?: string } };
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(false);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setCargando(true);
    try {
      const user = await login(email, password);
      const destino = location.state?.from
        ?? (user.rol ? INICIO_POR_ROL[user.rol.codigo] : "/")
        ?? "/";
      navigate(destino, { replace: true });
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const msg = er?.response?.data?.errors?.email?.[0]
        ?? er?.response?.data?.message
        ?? "No fue posible iniciar sesion.";
      setError(msg);
    } finally {
      setCargando(false);
    }
  };

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <h2 className="text-xl font-semibold text-institutional-800">Iniciar sesion</h2>
      <p className="text-sm text-muted-500">Usa tu correo institucional.</p>

      {error && <Alert tone="danger">{error}</Alert>}

      <Input label="Correo electronico" type="email" autoComplete="username"
             value={email} onChange={(e) => setEmail(e.target.value)} required />
      <PasswordInput label="Contraseña" autoComplete="current-password"
             value={password} onChange={(e) => setPassword(e.target.value)} required />

      <Button type="submit" loading={cargando} className="w-full">Ingresar</Button>

      <div className="flex justify-between text-sm">
        <Link to="/forgot-password" className="text-institutional-700 hover:underline">Olvide mi contraseña</Link>
        <Link to="/" className="text-muted-500 hover:underline">Volver al inicio</Link>
      </div>
    </form>
  );
}
