import { useState } from "react";
import { Link } from "react-router-dom";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { authService } from "../../services/authService";

export function ForgotPassword() {
  const [email, setEmail] = useState("");
  const [enviando, setEnviando] = useState(false);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMensaje(null); setError(null);
    setEnviando(true);
    try {
      const r = await authService.forgotPassword(email);
      setMensaje(r.message);
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      setError(er?.response?.data?.message ?? "No fue posible enviar el correo.");
    } finally {
      setEnviando(false);
    }
  };

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <h2 className="text-xl font-semibold text-institutional-800">Recuperar contraseña</h2>
      <p className="text-sm text-muted-500">Te enviaremos un enlace para reestablecer tu contraseña.</p>

      {mensaje && <Alert tone="success">{mensaje}</Alert>}
      {error && <Alert tone="danger">{error}</Alert>}

      <Input label="Correo electronico" type="email" value={email}
             onChange={(e) => setEmail(e.target.value)} required />
      <Button type="submit" loading={enviando} className="w-full">Enviar enlace</Button>
      <div className="text-sm text-center">
        <Link to="/login" className="text-institutional-700 hover:underline">Volver al login</Link>
      </div>
    </form>
  );
}
