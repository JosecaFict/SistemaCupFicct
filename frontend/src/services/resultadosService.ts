import { api } from "./api";
import type {
  FiltrosResultados,
  GestionCup,
  NotasPostulante,
  Paginated,
  Resultado,
  ResultadoKPIs,
} from "../types";

/*
 * resultadosService -- endpoints /api/resultados/* (ADMINISTRADOR + COORDINADOR).
 *
 * Vista de Resultados del CUP (Ciclo 2). Lista y KPIs filtrables por gestion,
 * carrera, estado, opcion y busqueda libre.
 */
function limpiar(params: FiltrosResultados): Record<string, string | number> {
  const out: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== "" && v !== null) {
      out[k] = v as string | number;
    }
  }
  return out;
}

export const resultadosService = {
  lista: (filtros: FiltrosResultados = {}, page = 1, perPage = 20) =>
    api
      .get<Paginated<Resultado>>("/api/resultados", {
        params: { ...limpiar(filtros), page, per_page: perPage },
      })
      .then((r) => r.data),

  kpis: (filtros: FiltrosResultados = {}) =>
    api
      .get<ResultadoKPIs>("/api/resultados/kpis", { params: limpiar(filtros) })
      .then((r) => r.data),

  gestiones: () =>
    api.get<GestionCup[]>("/api/resultados/gestiones").then((r) => r.data),

  notasPostulante: (postulacionId: number) =>
    api
      .get<NotasPostulante>(`/api/resultados/${postulacionId}/notas`)
      .then((r) => r.data),
};
