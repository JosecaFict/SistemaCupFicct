import { Badge } from "../ui/Badge";
import type { BadgeTone } from "../ui/Badge";
import {
  ESTADO_PAGO_META,
  ESTADO_POSTULACION_META,
  ESTADO_REQUISITO_META,
} from "../../data/constants";
import type { EstadoPago, EstadoPostulacion, EstadoRequisito } from "../../types";

// Badge especializado para los enums de estado del Ciclo 1.
export function StatusBadge({ estado, tipo }: {
  estado: string;
  tipo: "postulacion" | "pago" | "requisito";
}) {
  let meta: { label: string; tone: BadgeTone } = { label: estado, tone: "neutral" };

  if (tipo === "postulacion") {
    meta = ESTADO_POSTULACION_META[estado as EstadoPostulacion] ?? meta;
  } else if (tipo === "pago") {
    meta = ESTADO_PAGO_META[estado as EstadoPago] ?? meta;
  } else if (tipo === "requisito") {
    meta = ESTADO_REQUISITO_META[estado as EstadoRequisito] ?? meta;
  }

  return <Badge tone={meta.tone}>{meta.label}</Badge>;
}
