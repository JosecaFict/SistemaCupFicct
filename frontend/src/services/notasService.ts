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
  // Mapa numero_examen -> "YYYY-MM-DD" o null si el admin aun no la definio.
  fechas_examenes: Record<string, string | null>;
  // Mapa numero_examen -> true si today() >= fecha (habilita carga).
  habilitado_carga: Record<string, boolean>;
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
  bloqueado: boolean;
  fecha_examen: string | null;
  motivo_bloqueo: string | null;
}

export interface BloquePendiente {
  // ID sintetico para DataTable (no viene del backend, se genera en el frontend
  // combinando grupo+materia+examen).
  id: string;
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

  // Lista PDF de postulantes del grupo+materia (para control del docente en aula).
  descargarListaPdf: (grupo_id: number, gestion_materia_id: number) =>
    api
      .get(`/api/docente/grupo/${grupo_id}/materia/${gestion_materia_id}/lista-pdf`, {
        responseType: "blob",
      })
      .then((r) => r.data as Blob),

  // CU17 -- plantillas Excel de notas
  descargarPlantilla: (grupo_id: number, gestion_materia_id: number, numero_examen: number) =>
    api
      .get("/api/docente/notas/plantilla", {
        params: { grupo_id, gestion_materia_id, numero_examen },
        responseType: "blob",
      })
      .then((r) => r.data as Blob),

  importarNotas: (payload: { grupo_id: number; gestion_materia_id: number; numero_examen: number; archivo: File }) =>
    withCsrf(async () => {
      const fd = new FormData();
      fd.append("grupo_id", String(payload.grupo_id));
      fd.append("gestion_materia_id", String(payload.gestion_materia_id));
      fd.append("numero_examen", String(payload.numero_examen));
      fd.append("archivo", payload.archivo);
      return (await api.post("/api/docente/notas/importar", fd)).data;
    }),

  // -------- COORD + ADMIN --------
  bloquesPendientes: (gestion_id: number) =>
    api
      .get<Omit<BloquePendiente, "id">[]>("/api/notas/bloques-pendientes", { params: { gestion_id } })
      .then((r) =>
        r.data.map<BloquePendiente>((b) => ({
          ...b,
          // ID sintetico: el backend no devuelve uno, lo armamos aqui.
          id: `${b.grupo_id}-${b.gestion_materia_id}-${b.numero_examen}`,
        }))
      ),

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
