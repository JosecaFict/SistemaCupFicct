import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import type { Location } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { Input } from "../../components/ui/Input";
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
  const [verPassword, setVerPassword] = useState(false);
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
      <Input label="Contraseña" type={verPassword ? "text" : "password"} autoComplete="current-password"
             value={password} onChange={(e) => setPassword(e.target.value)} required
             rightSlot={
               <button type="button" onClick={() => setVerPassword((v) => !v)}
                       className="p-1 text-muted-500 hover:text-institutional-700"
                       aria-label={verPassword ? "Ocultar contraseña" : "Mostrar contraseña"}
                       title={verPassword ? "Ocultar contraseña" : "Mostrar contraseña"}>
                 {verPassword ? <IconOjoTachado /> : <IconOjo />}
               </button>
             } />

      <Button type="submit" loading={cargando} className="w-full">Ingresar</Button>

      <div className="flex justify-between text-sm">
        <Link to="/forgot-password" className="text-institutional-700 hover:underline">Olvide mi contraseña</Link>
        <Link to="/" className="text-muted-500 hover:underline">Volver al inicio</Link>
      </div>
    </form>
  );
}

/* Iconos de ojo (ver / ocultar contraseña), SVG inline sin dependencias. */
function IconOjo() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  );
}

function IconOjoTachado() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
      <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
      <line x1="2" y1="2" x2="22" y2="22" />
    </svg>
  );
}
