import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { authService } from "../../services/authService";

/*
 * ResetPassword
 * --------------------------------------------------------------------------
 * Paso 2 de la recuperacion: el usuario ingresa el codigo OTP que recibio
 * por correo, junto con su nueva contrasena.
 */
export function ResetPassword() {
  const [params] = useSearchParams();
  const [email, setEmail]       = useState(params.get("email") ?? "");
  const [codigo, setCodigo]     = useState("");
  const [password, setPassword] = useState("");
  const [confirmacion, setConfirmacion] = useState("");
  const [error, setError]     = useState<string | null>(null);
  const [exito, setExito]     = useState(false);
  const [enviando, setEnviando] = useState(false);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setEnviando(true);
    try {
      await authService.resetPassword({
        email, codigo, password, password_confirmation: confirmacion,
      });
      setExito(true);
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      setError(er?.response?.data?.message ?? "No fue posible reestablecer la contraseña.");
    } finally {
      setEnviando(false);
    }
  };

  if (exito) {
    return (
      <div className="space-y-4">
        <h2 className="text-xl font-semibold text-institutional-800">Contraseña actualizada</h2>
        <Alert tone="success">Tu contraseña fue reestablecida correctamente. Ya puedes iniciar sesión.</Alert>
        <Link to="/login" className="block text-center text-institutional-700 hover:underline">
          Ir al login
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <h2 className="text-xl font-semibold text-institutional-800">Reestablecer contraseña</h2>
      <p className="text-sm text-muted-500">
        Ingresa el código de 6 dígitos que enviamos a tu correo y tu nueva contraseña.
      </p>

      {error && <Alert tone="danger">{error}</Alert>}

      <Input label="Correo" type="email" value={email}
             onChange={(e) => setEmail(e.target.value)} required />
      <Input label="Código de 6 dígitos" inputMode="numeric" maxLength={6}
             value={codigo} onChange={(e) => setCodigo(e.target.value.replace(/\D/g, ""))}
             placeholder="------" required />
      <Input label="Nueva contraseña" type="password" value={password}
             onChange={(e) => setPassword(e.target.value)} required minLength={8} />
      <Input label="Confirmar contraseña" type="password" value={confirmacion}
             onChange={(e) => setConfirmacion(e.target.value)} required minLength={8} />

      <Button type="submit" loading={enviando} className="w-full">Reestablecer</Button>
      <div className="flex justify-between text-sm">
        <Link to="/forgot-password" className="text-institutional-700 hover:underline">Pedir otro código</Link>
        <Link to="/login" className="text-muted-500 hover:underline">Volver al login</Link>
      </div>
    </form>
  );
}
