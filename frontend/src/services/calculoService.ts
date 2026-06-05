import { api, withCsrf } from "./api";

/*
 * calculoService -- endpoints /api/calculo/* (CU16, CU17)
 *
 * Acceso: ADMINISTRADOR + COORDINADOR para calcular y publicar.
 * Solo ADMINISTRADOR puede despublicar.
 */

export interface EstadoCalculo {
  gestion_id: number;
  gestion_codigo: string;
  postulaciones: number;
  cantidad_materias: number;
  cantidad_examenes: number;
  notas_esperadas: number;
  notas_cargadas: number;
  notas_pendientes: number;
  notas_rechazadas: number;
  puede_calcularse: boolean;
  razon_bloqueo: string | null;
  resultados_calculados: number;
  resultados_publicados: number;
  tiene_calculo_previo: boolean;
  tiene_publicacion: boolean;
}

export interface ResumenCalculo {
  total: number;
  aceptados: number;
  reprobados: number;
  sin_cupo: number;
  cupos_disponibles: Record<string, number>;
  cupos_restantes: Record<string, number>;
}

export const calculoService = {
  estado: (gestionId: number) =>
    api.get<EstadoCalculo>("/api/calculo/estado", { params: { gestion_id: gestionId } })
       .then((r) => r.data),

  calcular: (gestionId: number) =>
    withCsrf(async () => {
      const r = await api.post<{ mensaje: string; resumen: ResumenCalculo; estado: EstadoCalculo }>(
        "/api/calculo/calcular",
        { gestion_id: gestionId }
      );
      return r.data;
    }),

  publicar: (gestionId: number) =>
    withCsrf(async () => {
      const r = await api.post<{ mensaje: string; cantidad: number; estado: EstadoCalculo }>(
        "/api/calculo/publicar",
        { gestion_id: gestionId }
      );
      return r.data;
    }),

  despublicar: (gestionId: number) =>
    withCsrf(async () => {
      const r = await api.post<{ mensaje: string; cantidad: number }>(
        "/api/calculo/despublicar",
        { gestion_id: gestionId }
      );
      return r.data;
    }),
};
