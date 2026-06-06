import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { authService } from "../../services/authService";

export function ResetPassword() {
  const [params] = useSearchParams();
  const [email, setEmail]       = useState(params.get("email") ?? "");
  const [token]                  = useState(params.get("token") ?? "");
  const [password, setPassword] = useState("");
  const [confirmacion, setConfirmacion] = useState("");
  const [error, setError]     = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null); setMensaje(null);
    setEnviando(true);
    try {
      const r = await authService.resetPassword({
        token, email, password, password_confirmation: confirmacion,
      });
      setMensaje(r.message);
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      setError(er?.response?.data?.message ?? "No fue posible reestablecer la contraseña.");
    } finally {
      setEnviando(false);
    }
  };

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <h2 className="text-xl font-semibold text-institutional-800">Reestablecer contraseña</h2>

      {mensaje && <Alert tone="success">{mensaje}</Alert>}
      {error && <Alert tone="danger">{error}</Alert>}

      <Input label="Correo" type="email" value={email}
             onChange={(e) => setEmail(e.target.value)} required />
      <Input label="Nueva contraseña" type="password" value={password}
             onChange={(e) => setPassword(e.target.value)} required minLength={8} />
      <Input label="Confirmar contraseña" type="password" value={confirmacion}
             onChange={(e) => setConfirmacion(e.target.value)} required minLength={8} />

      <Button type="submit" loading={enviando} className="w-full">Reestablecer</Button>
      <div className="text-sm text-center">
        <Link to="/login" className="text-institutional-700 hover:underline">Volver al login</Link>
      </div>
    </form>
  );
}
