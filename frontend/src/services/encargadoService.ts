import { api, withCsrf } from "./api";
import type { BoletaPayload, Grupo, Paginated, Postulacion, PostulacionRequisito } from "../types";

/*
 * encargadoService -- endpoints /api/encargado/* (ADMINISTRADOR + ENCARGADO).
 * Postulantes, requisitos, confirmacion de inscripcion, boleta y grupos.
 */
export const encargadoService = {
  postulaciones: (params: Record<string, string | number> = {}) =>
    api.get<Paginated<Postulacion>>("/api/encargado/postulaciones", { params }).then((r) => r.data),

  postulacion: (id: number) =>
    api.get<Postulacion>(`/api/encargado/postulaciones/${id}`).then((r) => r.data),

  anularPostulacion: (id: number, motivo: string) =>
    withCsrf(async () => (await api.post(`/api/encargado/postulaciones/${id}/anular`, { motivo })).data),

  requisitos: (postulacionId: number) =>
    api.get<PostulacionRequisito[]>(`/api/encargado/postulaciones/${postulacionId}/requisitos`).then((r) => r.data),

  marcarRequisito: (prId: number, estado: string, observacion?: string) =>
    withCsrf(async () => (await api.patch(`/api/encargado/postulacion-requisitos/${prId}`, {
      estado,
      observacion: observacion ?? null,
    })).data),

  requisitosCompletados: (postulacionId: number) =>
    api.get<{ todos_completados: boolean }>(
      `/api/encargado/postulaciones/${postulacionId}/requisitos/completados`
    ).then((r) => r.data),

  confirmar: (postulacionId: number, turnoId: number) =>
    withCsrf(async () => {
      const { data } = await api.post<{ message: string; inscripcion: unknown; boleta: BoletaPayload }>(
        `/api/encargado/postulaciones/${postulacionId}/confirmar`,
        { turno_id: turnoId }
      );
      return data;
    }),

  boleta: (postulacionId: number) =>
    api.get<BoletaPayload>(`/api/encargado/postulaciones/${postulacionId}/boleta`).then((r) => r.data),

  grupos: (filtros: { gestion_cup_id?: number; turno_id?: number } = {}) => {
    const params: Record<string, number> = {};
    if (filtros.gestion_cup_id) params.gestion_cup_id = filtros.gestion_cup_id;
    if (filtros.turno_id)       params.turno_id       = filtros.turno_id;
    return api.get<Grupo[]>("/api/encargado/grupos", { params }).then((r) => r.data);
  },
};
