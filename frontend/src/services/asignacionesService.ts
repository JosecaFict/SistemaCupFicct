import { api, withCsrf } from "./api";
import type { Paginated } from "../types";

/*
 * asignacionesService -- endpoints /api/asignaciones-docente/*
 *
 * Acceso: ADMINISTRADOR + COORDINADOR
 * CU12-CU13 del Ciclo 2.
 */

export interface AsignacionDocente {
  id: number;
  gestion_cup_id: number;
  grupo_id: number;
  gestion_materia_id: number;
  docente_user_id: number;
  ambiente_id: number | null;
  dias_semana: string;
  hora_inicio: string;
  hora_fin: string;
  gestion?: { id: number; codigo: string; nombre: string };
  grupo?: {
    id: number;
    codigo: string;
    capacidad: number;
    turno?: { codigo: string; nombre: string };
  };
  gestion_materia?: {
    id: number;
    ponderacion: number;
    materia?: { id: number; codigo: string; nombre: string };
  };
  docente?: { id: number; nombre: string; apellidos: string; email: string };
  ambiente?: { id: number; nombre: string; ubicacion: string | null; capacidad: number | null } | null;
}

export interface DatosIniciales {
  grupos: Array<{
    id: number;
    codigo: string;
    capacidad: number;
    inscritos_actuales: number;
    turno_id: number;
    ambiente_id: number | null;
    turno?: { id: number; codigo: "M" | "T" | "N"; nombre: string };
  }>;
  gestion_materias: Array<{
    id: number;
    materia_id: number;
    ponderacion: number;
    materia?: { id: number; codigo: string; nombre: string };
  }>;
  bloques_por_turno: Record<"M" | "T" | "N", Array<[string, string]>>;
  patrones_dias: Record<string, string>;
}

export interface RecursosDisponibles {
  docentes_disponibles: Array<{ id: number; nombre: string; apellidos: string; email: string }>;
  ambientes_disponibles: Array<{ id: number; nombre: string; ubicacion: string | null; capacidad: number | null }>;
  grupos_ocupados_en_franja: number[];
  materias_ya_asignadas_al_grupo: number[];
}

export interface FiltrosAsignaciones {
  gestion_id?: number;
  grupo_id?: number;
  docente_id?: number;
  materia_id?: number;
  turno_id?: number;
}

export interface CatalogosFiltros {
  turnos: Array<{ id: number; codigo: "M" | "T" | "N"; nombre: string }>;
  materias: Array<{
    gestion_materia_id: number;
    materia_id: number;
    codigo: string;
    nombre: string;
  }>;
  docentes: Array<{ id: number; nombre: string; apellidos: string; email: string }>;
}

export interface PayloadAsignacion {
  gestion_cup_id: number;
  grupo_id: number;
  gestion_materia_id: number;
  docente_user_id: number;
  ambiente_id: number | null;
  dias_semana: string;
  hora_inicio: string;
  hora_fin: string;
}

function limpiar(p: Record<string, unknown>): Record<string, string | number> {
  const out: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(p)) {
    if (v !== undefined && v !== null && v !== "") {
      out[k] = v as string | number;
    }
  }
  return out;
}

export const asignacionesService = {
  lista: (filtros: FiltrosAsignaciones = {}) =>
    api
      .get<Paginated<AsignacionDocente>>("/api/asignaciones-docente", { params: limpiar(filtros) })
      .then((r) => r.data),

  datosIniciales: (gestionId: number) =>
    api
      .get<DatosIniciales>("/api/asignaciones-docente/datos-iniciales", {
        params: { gestion_id: gestionId },
      })
      .then((r) => r.data),

  catalogosFiltros: (gestionId: number) =>
    api
      .get<CatalogosFiltros>("/api/asignaciones-docente/catalogos-filtros", {
        params: { gestion_id: gestionId },
      })
      .then((r) => r.data),

  recursosDisponibles: (params: {
    gestion_id: number;
    dias_semana: string;
    hora_inicio: string;
    hora_fin: string;
    grupo_id?: number;
    ignore_id?: number;
  }) =>
    api
      .get<RecursosDisponibles>("/api/asignaciones-docente/recursos-disponibles", {
        params: limpiar(params),
      })
      .then((r) => r.data),

  crear: (payload: PayloadAsignacion) =>
    withCsrf(async () => (await api.post<AsignacionDocente>("/api/asignaciones-docente", payload)).data),

  actualizar: (id: number, payload: PayloadAsignacion) =>
    withCsrf(async () => (await api.put<AsignacionDocente>(`/api/asignaciones-docente/${id}`, payload)).data),

  borrar: (id: number) =>
    withCsrf(async () => (await api.delete(`/api/asignaciones-docente/${id}`)).data),
};
