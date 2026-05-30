// Constantes compartidas Sistema CUP FICCT (Ciclo 1)

import type { EstadoPostulacion, EstadoPago, EstadoRequisito, RolCodigo } from "../types";

export const APP_NAME = import.meta.env.VITE_APP_NAME ?? "Sistema CUP FICCT";

// Departamentos para "expedido" del CI Bolivia
export const EXPEDIDOS_BO = ["SC", "LP", "CB", "OR", "PT", "TJ", "CH", "BN", "PD"] as const;

// Etiquetas y colores de estados para Badges
export const ESTADO_POSTULACION_META: Record<EstadoPostulacion, { label: string; tone: "neutral" | "info" | "success" | "warning" | "danger" }> = {
  PREINSCRITO:         { label: "Preinscrito",         tone: "info" },
  FORMULARIO_GENERADO: { label: "Formulario generado", tone: "info" },
  PAGO_APROBADO:       { label: "Pago aprobado",       tone: "success" },
  OBSERVADO:           { label: "Observado",           tone: "warning" },
  INSCRITO:            { label: "Inscrito",            tone: "success" },
  ANULADO:             { label: "Anulado",             tone: "danger" },
};

export const ESTADO_PAGO_META: Record<EstadoPago, { label: string; tone: "neutral" | "info" | "success" | "warning" | "danger" }> = {
  PENDIENTE: { label: "Pendiente", tone: "warning" },
  APROBADO:  { label: "Aprobado",  tone: "success" },
  RECHAZADO: { label: "Rechazado", tone: "danger" },
  CANCELADO: { label: "Cancelado", tone: "neutral" },
};

export const ESTADO_REQUISITO_META: Record<EstadoRequisito, { label: string; tone: "neutral" | "info" | "success" | "warning" | "danger" }> = {
  PENDIENTE: { label: "Pendiente", tone: "neutral" },
  VALIDADO:  { label: "Validado",  tone: "success" },
  OBSERVADO: { label: "Observado", tone: "warning" },
  RECHAZADO: { label: "Rechazado", tone: "danger" },
};

// Ruta inicial sugerida por rol (despues del login)
export const INICIO_POR_ROL: Record<RolCodigo, string> = {
  ADMINISTRADOR: "/admin",
  ENCARGADO:     "/encargado",
  DOCENTE:       "/docente",
  COORDINADOR:   "/coordinador",
};
