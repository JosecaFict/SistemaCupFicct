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
  carrera_primera: string;
  carrera_segunda: string | null;
  modalidad: string | null;
  aula_o_enlace: string | null;
  fecha_inscripcion: string | null;
  confirmada_por: string | null;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
