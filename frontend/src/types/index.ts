// Tipos del dominio Sistema CUP FICCT (Ciclo 1)
// Espejo de los modelos Eloquent del backend.

export type RolCodigo = "ADMINISTRADOR" | "ENCARGADO" | "DOCENTE" | "COORDINADOR";

export interface Rol {
  id: number;
  codigo: RolCodigo;
  nombre: string;
  descripcion: string | null;
}

export interface User {
  id: number;
  role_id: number;
  nombre: string;
  apellidos: string;
  fecha_nacimiento?: string | null;
  ci?: string | null;
  telefono?: string | null;
  descripcion?: string | null;
  email: string;
  activo: boolean;
  last_login_at: string | null;
  rol?: Rol;
}

export interface Carrera {
  id: number;
  codigo: string;
  nombre: string;
  activa: boolean;
}

export interface Materia {
  id: number;
  codigo: "MAT" | "FIS" | "ING" | "COMP";
  nombre: string;
  activa: boolean;
}

export interface Turno {
  id: number;
  codigo: "M" | "T" | "N";
  nombre: string;
  hora_inicio: string | null;
  hora_fin: string | null;
  activo: boolean;
}

export interface GestionCup {
  id: number;
  codigo: string;
  nombre: string;
  fecha_inicio_preinscripcion: string;
  fecha_cierre_preinscripcion: string;
  cantidad_examenes: number;
  capacidad_maxima_grupo: number;
  estimado_postulantes: number;
  turnos_habilitados: string;
  estado: "BORRADOR" | "ACTIVA" | "CERRADA";
  costo_inscripcion?: number | string;
}

export interface Grupo {
  id: number;
  gestion_cup_id: number;
  turno_id: number;
  ambiente_id: number | null;
  codigo: string;
  capacidad: number;
  inscritos_actuales: number;
  estado: "ACTIVO" | "INACTIVO";
  turno?: Turno;
  gestion?: GestionCup;
}

export type TipoDocumento = "CI_BO" | "EXT";

export interface Persona {
  id: number;
  tipo_documento: TipoDocumento;
  documento: string;
  expedido: string | null;
  nombre: string;
  apellido_paterno: string | null;
  apellido_materno: string | null;
  sexo: "M" | "F" | "O" | null;
  fecha_nacimiento: string | null;
  email: string | null;
  telefono: string | null;
  direccion: string | null;
}

export type EstadoPostulacion =
  | "PREINSCRITO"
  | "FORMULARIO_GENERADO"
  | "PAGO_APROBADO"
  | "OBSERVADO"
  | "INSCRITO"
  | "ANULADO";

export interface Postulacion {
  id: number;
  persona_id: number;
  gestion_cup_id: number;
  colegio_id: number | null;
  anio_egreso_colegio: number | null;
  carrera_primera_id: number;
  carrera_segunda_id: number | null;
  turno_id: number | null;
  grupo_id: number | null;
  codigo_postulante: string | null;
  estado: EstadoPostulacion;
  fecha_preinscripcion: string | null;
  fecha_inscripcion: string | null;
  fecha_anulacion: string | null;
  observacion: string | null;
  persona?: Persona;
  gestion?: GestionCup;
  carrera_primera?: Carrera;
  carrera_segunda?: Carrera | null;
  turno?: Turno | null;
  grupo?: Grupo | null;
  pagos?: Pago[];
}

export type EstadoPago = "PENDIENTE" | "APROBADO" | "RECHAZADO" | "CANCELADO";

export interface Pago {
  id: number;
  postulacion_id: number;
  monto: string;
  moneda: string;
  modo: "simulated" | "test" | "live";
  estado: EstadoPago;
  stripe_client_secret: string | null;
  referencia: string | null;
}

/* Datos listos para imprimir el comprobante de pago (CU7). */
export interface ComprobantePago {
  referencia: string | null;
  monto: string;
  moneda: string;
  modo: "simulated" | "test" | "live";
  estado: EstadoPago;
  fecha_aprobacion: string | null;
  postulante: string;
  gestion: string;
  concepto: string;
}

export type EstadoRequisito = "PENDIENTE" | "VALIDADO" | "OBSERVADO" | "RECHAZADO";

export interface Requisito {
  id: number;
  codigo: string;
  nombre: string;
  obligatorio: boolean;
  orden: number;
}

export interface PostulacionRequisito {
  id: number;
  postulacion_id: number;
  requisito_id: number;
  estado: EstadoRequisito;
  observacion: string | null;
  verificado_at: string | null;
  requisito?: Requisito;
  verificado_por?: User | null;
}

export interface BoletaPayload {
  codigo_postulante: string;
  nombre_completo: string;
  documento: { tipo: string; numero: string; expedido: string | null };
  gestion: { codigo: string; nombre: string };
  turno: { codigo: string; nombre: string; horario: string };
  grupo: { codigo: string; capacidad: number };
  horario: { materia: string; dias: string; hora: string; aula: string }[];
  carrera_primera: string;
  carrera_segunda: string | null;
  modalidad: string | null;
  aula_o_enlace: string | null;
  fecha_inscripcion: string | null;
  confirmada_por: string | null;
}

export interface NotasPostulante {
  codigo: string | null;
  nombre: string | null;
  gestion: string | null;
  cantidad_examenes: number;
  resultado: {
    nota_final: string | number;
    ranking_global: number | null;
    estado_final: string;
    motivo: string | null;
  } | null;
  materias: {
    materia_codigo: string;
    materia_nombre: string;
    ponderacion: number | null;
    examenes: Record<string, number>;
    promedio: number | null;
  }[];
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/* -------- Ciclo 2: Resultados -------- */

export type EstadoResultado = "PENDIENTE_DESEMPATE" | "ACEPTADO" | "REPROBADO" | "SIN_CUPO";
export type OpcionAceptada = "PRIMERA" | "SEGUNDA" | "NINGUNA";

export interface Resultado {
  id: number;
  postulacion_id: number;
  nota_final: string | number;
  ranking_global: number | null;
  carrera_asignada_id: number | null;
  opcion_aceptada: OpcionAceptada | null;
  estado_final: EstadoResultado;
  motivo: string | null;
  fecha_calculo: string;
  publicado: boolean;
  fecha_publicacion: string | null;
  postulacion?: {
    id: number;
    codigo_postulante: string | null;
    persona?: Persona;
    gestion?: GestionCup;
    carrera_primera?: Carrera;
    carrera_segunda?: Carrera | null;
  };
  carrera_asignada?: Carrera | null;
}

export interface ResultadoKPIs {
  totales: {
    aceptados: number;
    primera_opcion: number;
    segunda_opcion: number;
    reprobados: number;
    sin_cupo: number;
    pendiente_desempate: number;
    total: number;
  };
  por_carrera: Array<{
    carrera_id: number;
    carrera_codigo: string | null;
    carrera_nombre: string | null;
    cantidad: number;
  }>;
}

export interface FiltrosResultados {
  gestion_id?: number | "";
  carrera_id?: number | "";
  estado?: EstadoResultado | "TODOS" | "";
  opcion?: OpcionAceptada | "";
  q?: string;
}
