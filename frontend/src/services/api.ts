import axios from "axios";

/*
 * Cliente Axios central
 * --------------------------------------------------------------------------
 * - baseURL apunta al backend Laravel (configurable via VITE_API_URL).
 * - withCredentials: true es OBLIGATORIO para que Sanctum reciba la cookie
 *   de sesion al hacer login. El backend tiene CORS supports_credentials.
 * - Antes de cada peticion no-GET pasamos por /sanctum/csrf-cookie para
 *   refrescar el token CSRF (XSRF-TOKEN).
 */
/*
 * En produccion (Vercel) el frontend usa URL relativa para que las requests
 * a /api/* y /sanctum/* pasen por el proxy configurado en vercel.json. Asi
 * el navegador trata las cookies como mismo origen y CSRF funciona bien.
 *
 * En desarrollo (Laragon o artisan serve) se usa VITE_API_URL apuntando al
 * backend local. Si no esta definida, se cae al default localhost:8000.
 */
const BASE = import.meta.env.VITE_API_URL ?? "http://localhost:8000";

export const api = axios.create({
  baseURL: BASE,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
});

let csrfReady = false;

export async function ensureCsrf(): Promise<void> {
  if (csrfReady) return;
  await api.get("/sanctum/csrf-cookie");
  csrfReady = true;
}

/** Helper para peticiones con CSRF (login, POST, PUT, PATCH, DELETE). */
async function withCsrf<T>(fn: () => Promise<T>): Promise<T> {
  await ensureCsrf();
  return fn();
}

api.interceptors.response.use(
  (resp) => resp,
  (err) => {
    // Si la sesion expiro, invalidar el flag CSRF para refrescarlo en la
    // proxima peticion (en lugar de quedarse pegado).
    if (err?.response?.status === 419) {
      csrfReady = false;
    }
    return Promise.reject(err);
  }
);

export { withCsrf };
