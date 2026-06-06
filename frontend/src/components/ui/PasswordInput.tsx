import { useState } from "react";
import type { InputHTMLAttributes } from "react";
import { Input } from "./Input";

/*
 * PasswordInput
 * --------------------------------------------------------------------------
 * Campo de contraseña con boton de ojo para mostrar/ocultar el texto.
 * Reutiliza Input (con su prop rightSlot). Reenvia todas las props normales
 * de un input (value, onChange, required, minLength, autoComplete, label...).
 */
type Props = Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & {
  label?: string;
  error?: string;
  hint?: string;
};

export function PasswordInput(props: Props) {
  const [ver, setVer] = useState(false);

  return (
    <Input
      {...props}
      type={ver ? "text" : "password"}
      rightSlot={
        <button
          type="button"
          onClick={() => setVer((v) => !v)}
          className="p-1 text-muted-500 hover:text-institutional-700"
          aria-label={ver ? "Ocultar contraseña" : "Mostrar contraseña"}
          title={ver ? "Ocultar contraseña" : "Mostrar contraseña"}
        >
          {ver ? <IconOjoTachado /> : <IconOjo />}
        </button>
      }
    />
  );
}

/* Iconos de ojo (ver / ocultar), SVG inline sin dependencias. */
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
