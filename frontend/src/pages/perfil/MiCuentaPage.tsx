import { useState } from "react";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { PasswordInput } from "../../components/ui/PasswordInput";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { useAuth } from "../../context/AuthContext";
import { authService } from "../../services/authService";

// Misma politica que el reset: 8+, mayuscula, minuscula y numero.
const POLITICA_PASSWORD = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

/*
 * MiCuentaPage
 * --------------------------------------------------------------------------
 * Autogestion del usuario autenticado (cualquier rol):
 *   - Editar sus datos personales (nombre, apellidos, email).
 *   - Cambiar su contrasena (pidiendo la actual).
 */
export function MiCuentaPage() {
  const { user, refresh } = useAuth();

  // ----- Datos personales -----
  const [nombre, setNombre]       = useState(user?.nombre ?? "");
  const [apellidos, setApellidos] = useState(user?.apellidos ?? "");
  const [email, setEmail]         = useState(user?.email ?? "");
  const [guardandoDatos, setGuardandoDatos] = useState(false);
  const [okDatos, setOkDatos]   = useState<string | null>(null);
  const [errDatos, setErrDatos] = useState<string | null>(null);

  const guardarDatos = async (e: React.FormEvent) => {
    e.preventDefault();
    setOkDatos(null); setErrDatos(null);
    setGuardandoDatos(true);
    try {
      await authService.actualizarPerfil({ nombre, apellidos, email });
      await refresh();
      setOkDatos("Datos actualizados correctamente.");
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      setErrDatos(er?.response?.data?.message ?? "No fue posible actualizar tus datos.");
    } finally {
      setGuardandoDatos(false);
    }
  };

  // ----- Cambio de contrasena -----
  const [actual, setActual]   = useState("");
  const [nueva, setNueva]     = useState("");
  const [confirma, setConfirma] = useState("");
  const [cambiando, setCambiando] = useState(false);
  const [okPass, setOkPass]   = useState<string | null>(null);
  const [errPass, setErrPass] = useState<string | null>(null);

  const cambiarPass = async (e: React.FormEvent) => {
    e.preventDefault();
    setOkPass(null); setErrPass(null);

    if (!POLITICA_PASSWORD.test(nueva)) {
      setErrPass("La nueva contraseña debe tener mínimo 8 caracteres, e incluir al menos una mayúscula, una minúscula y un número.");
      return;
    }
    if (nueva !== confirma) {
      setErrPass("Las contraseñas no coinciden.");
      return;
    }

    setCambiando(true);
    try {
      const r = await authService.cambiarPassword({
        current_password: actual,
        password: nueva,
        password_confirmation: confirma,
      });
      setOkPass(r.message);
      setActual(""); setNueva(""); setConfirma("");
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const msg = er?.response?.data?.errors?.current_password?.[0]
        ?? er?.response?.data?.message
        ?? "No fue posible cambiar la contraseña.";
      setErrPass(msg);
    } finally {
      setCambiando(false);
    }
  };

  return (
    <div className="space-y-6 max-w-2xl">
      <div>
        <h1 className="text-2xl font-semibold text-institutional-800">Mi cuenta</h1>
        <p className="text-sm text-muted-500">
          Actualiza tus datos personales y tu contraseña.
          {user?.rol && <span className="ml-1">Rol: <b>{user.rol.nombre}</b>.</span>}
        </p>
      </div>

      {/* Datos personales */}
      <Card title="Datos personales">
        <form onSubmit={guardarDatos} className="space-y-4">
          {okDatos && <Alert tone="success">{okDatos}</Alert>}
          {errDatos && <Alert tone="danger">{errDatos}</Alert>}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Input label="Nombre" value={nombre} onChange={(e) => setNombre(e.target.value)} required maxLength={100} />
            <Input label="Apellidos" value={apellidos} onChange={(e) => setApellidos(e.target.value)} required maxLength={100} />
          </div>
          <Input label="Correo electrónico" type="email" value={email}
                 onChange={(e) => setEmail(e.target.value)} required />

          <div className="flex justify-end">
            <Button type="submit" loading={guardandoDatos}>Guardar datos</Button>
          </div>
        </form>
      </Card>

      {/* Cambio de contrasena */}
      <Card title="Cambiar contraseña">
        <form onSubmit={cambiarPass} className="space-y-4">
          {okPass && <Alert tone="success">{okPass}</Alert>}
          {errPass && <Alert tone="danger">{errPass}</Alert>}

          <PasswordInput label="Contraseña actual" value={actual}
                 onChange={(e) => setActual(e.target.value)} required
                 autoComplete="current-password" />
          <PasswordInput label="Nueva contraseña" value={nueva}
                 onChange={(e) => setNueva(e.target.value)} required minLength={8}
                 autoComplete="new-password"
                 hint="Mínimo 8 caracteres, con al menos una mayúscula, una minúscula y un número." />
          <PasswordInput label="Confirmar nueva contraseña" value={confirma}
                 onChange={(e) => setConfirma(e.target.value)} required minLength={8}
                 autoComplete="new-password" />

          <div className="flex justify-end">
            <Button type="submit" loading={cambiando}>Cambiar contraseña</Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
