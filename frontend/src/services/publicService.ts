import { api, withCsrf } from "./api";
import type { Carrera, ComprobantePago, GestionCup, Pago, Postulacion, Turno } from "../types";

/*
 * publicService -- endpoints /api/public/* (sin login).
 * Usado por: Landing, Preinscripcion, FormularioGenerado, Pago, Resultados.
 */
export const publicService = {
  carreras:  () => api.get<Carrera[]>("/api/public/carreras").then((r) => r.data),
  turnos:    () => api.get<Turno[]>("/api/public/turnos").then((r) => r.data),
  gestionActiva: () => api.get<GestionCup | null>("/api/public/gestion-activa").then((r) => r.data),

  // CU5
  preinscribir: (payload: Record<string, unknown>) =>
    withCsrf(async () => {
      const { data } = await api.post<{ message: string; postulacion: Postulacion }>(
        "/api/public/preinscripciones",
        payload
      );
      return data;
    }),

  obtenerPostulacion: (id: number) =>
    api.get<Postulacion>(`/api/public/preinscripciones/${id}`).then((r) => r.data),

  buscarPorDocumento: (payload: { tipo_documento: "CI_BO" | "EXT"; documento: string; expedido?: string | null }) =>
    withCsrf(async () => {
      const { data } = await api.post<{ postulacion: Postulacion }>(
        "/api/public/preinscripciones/buscar",
        payload
      );
      return data;
    }),

  // CU6
  generarFormulario: (postulacionId: number) =>
    withCsrf(async () => {
      const { data } = await api.post<{ message: string; postulacion: Postulacion }>(
        `/api/public/preinscripciones/${postulacionId}/generar-formulario`
      );
      return data;
    }),

  // CU7 -- pago (simulado o Stripe segun STRIPE_MODE del backend)
  iniciarPago: (postulacionId: number) =>
    withCsrf(async () => {
      const { data } = await api.post<{
        pago: Pago;
        modo: string;
        client_secret: string;
        checkout_url: string | null;
        tarjetas_de_prueba: Record<string, string>;
      }>("/api/public/pagos/iniciar", { postulacion_id: postulacionId });
      return data;
    }),

  confirmarPago: (pagoId: number, tarjetaSimulada: string) =>
    withCsrf(async () => {
      const { data } = await api.post<{ pago: Pago; postulacion: Postulacion; comprobante: ComprobantePago }>(
        `/api/public/pagos/${pagoId}/confirmar`,
        { tarjeta_simulada: tarjetaSimulada }
      );
      return data;
    }),

  consultarResultado: (codigo: string) =>
    api.get(`/api/public/resultados`, { params: { codigo } }).then((r) => r.data),
};
