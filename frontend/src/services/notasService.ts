import { api, withCsrf } from "./api";

/*
 * notasService -- endpoints relacionados con notas (CU14, CU15).
 *
 * Para DOCENTE: ver sus asignaciones y cargar notas.
 * Para COORDINADOR + ADMIN: ver bloques pendientes y validarlos/rechazarlos.
 */

export interface MiAsignacion {
  id: number;
  gestion?: { id: number; codigo: string; nombre: string; cantidad_examenes: number; nota_minima_aprobacion: string | number };
  grupo?: { id: number; codigo: string; capacidad: number; turno?: { codigo: string; nombre: string } };
  gestion_materia?: { id: number; materia?: { id: number; codigo: string; nombre: string } };
  ambiente?: { id: number; nombre: string; ubicacion: string | null } | null;
  dias_semana: string;
  hora_inicio: string;
  hora_fin: string;
  total_postulantes: number;
  numero_examenes: number;
  progreso: Record<string, {
    numero_examen: number;
    cant: number;
    pendientes: number;
    validadas: number;
    rechazadas: number;
  }>;
}

export interface NotaFila {
  numero: number;
  postulacion_id: number;
  codigo_postulante: string | null;
  nombre_completo: string;
  documento: string | null;
  nota_id: number | null;
  valor: number | null;
  estado: "PENDIENTE" | "VALIDADA" | "RECHAZADA" | undefined;
  descalifica: boolean;
  observacion: string | null;
}

export interface NotasExamenResponse {
  asignacion: {
    id: number;
    grupo?: { codigo: string; turno?: { codigo: string; nombre: string } };
    gestion_materia?: { materia?: { codigo: string; nombre: string } };
    gestion?: { codigo: string; cantidad_examenes: number; nota_minima_aprobacion: string };
  };
  examen: number;
  postulantes: NotaFila[];
}

export interface BloquePendiente {
  grupo_id: number;
  grupo_codigo: string;
  turno_codigo: string;
  gestion_materia_id: number;
  materia_codigo: string;
  materia_nombre: string;
  numero_examen: number;
  total_notas: number;
  pendientes: number;
  validadas: number;
  rechazadas: number;
  docente: string | null;
  ultima_carga: string | null;
}

export const notasService = {
  // -------- DOCENTE --------
  misAsignaciones: (gestion_id?: number) =>
    api
      .get<MiAsignacion[]>("/api/docente/mis-asignaciones", { params: gestion_id ? { gestion_id } : {} })
      .then((r) => r.data),

  notasDelExamen: (grupoId: number, gmId: number, examen: number) =>
    api
      .get<NotasExamenResponse>(`/api/docente/grupo/${grupoId}/materia/${gmId}/examen/${examen}`)
      .then((r) => r.data),

  guardarNotas: (payload: {
    grupo_id: number;
    gestion_materia_id: number;
    numero_examen: number;
    notas: Array<{ postulacion_id: number; valor: number }>;
  }) =>
    withCsrf(async () => (await api.post("/api/docente/notas/guardar", payload)).data),

  // -------- COORD + ADMIN --------
  bloquesPendientes: (gestion_id: number) =>
    api
      .get<BloquePendiente[]>("/api/notas/bloques-pendientes", { params: { gestion_id } })
      .then((r) => r.data),

  validarBloque: (payload: {
    gestion_id: number;
    grupo_id: number;
    gestion_materia_id: number;
    numero_examen: number;
  }) =>
    withCsrf(async () => (await api.post("/api/notas/validar-bloque", payload)).data),

  rechazarBloque: (payload: {
    gestion_id: number;
    grupo_id: number;
    gestion_materia_id: number;
    numero_examen: number;
    observacion: string;
  }) =>
    withCsrf(async () => (await api.post("/api/notas/rechazar-bloque", payload)).data),
};
