import { api, withCsrf } from "./api";
import type { GestionCup, Grupo, Paginated, Rol, User } from "../types";

/*
 * adminService -- endpoints /api/admin/* (solo ADMINISTRADOR).
 * Usuarios, roles, gestiones CUP, generacion de grupos, bitacora.
 */
export const adminService = {
  roles:    () => api.get<Rol[]>("/api/admin/roles").then((r) => r.data),

  usuarios: (params: Record<string, string | number> = {}) =>
    api.get<Paginated<User>>("/api/admin/usuarios", { params }).then((r) => r.data),

  crearUsuario: (payload: Partial<User> & { password: string }) =>
    withCsrf(async () => (await api.post<User>("/api/admin/usuarios", payload)).data),

  actualizarUsuario: (id: number, payload: Partial<User> & { password?: string }) =>
    withCsrf(async () => (await api.put<User>(`/api/admin/usuarios/${id}`, payload)).data),

  toggleActivoUsuario: (id: number) =>
    withCsrf(async () => (await api.patch(`/api/admin/usuarios/${id}/toggle-activo`)).data),

  gestiones: (params: Record<string, string | number> = {}) =>
    api.get<Paginated<GestionCup>>("/api/admin/gestiones", { params }).then((r) => r.data),

  gestion: (id: number) =>
    api.get<GestionCup>(`/api/admin/gestiones/${id}`).then((r) => r.data),

  crearGestion: (payload: Record<string, unknown>) =>
    withCsrf(async () => (await api.post<GestionCup>("/api/admin/gestiones", payload)).data),

  actualizarGestion: (id: number, payload: Record<string, unknown>) =>
    withCsrf(async () => (await api.put<GestionCup>(`/api/admin/gestiones/${id}`, payload)).data),

  generarGrupos: (gestionId: number, forzar = false) =>
    withCsrf(async () => {
      const { data } = await api.post<{ message: string; resumen: Record<string, number>; grupos: Grupo[] }>(
        `/api/admin/gestiones/${gestionId}/generar-grupos`,
        { forzar }
      );
      return data;
    }),

  bitacora: (params: Record<string, string | number> = {}) =>
    api.get("/api/admin/bitacora", { params }).then((r) => r.data),
};
