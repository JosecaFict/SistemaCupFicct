<?php

namespace App\Enums;

/*
| Enum EstadoPostulacion -- Flujo Ciclo 1 + Ciclo 2.
|
| Ciclo 1:
|   PREINSCRITO -> FORMULARIO_GENERADO -> PAGO_APROBADO -> [OBSERVADO?] -> INSCRITO
|   ANULADO puede llegar desde cualquier estado anterior a INSCRITO.
|
| Ciclo 2 (despues del calculo de resultados):
|   INSCRITO -> ACEPTADO   (entro a una carrera)
|   INSCRITO -> SIN_CUPO   (no entro: cupos llenos, nota insuficiente
|                           o descalificacion).
*/
enum EstadoPostulacion: string
{
    case PREINSCRITO         = 'PREINSCRITO';
    case FORMULARIO_GENERADO = 'FORMULARIO_GENERADO';
    case PAGO_APROBADO       = 'PAGO_APROBADO';
    case OBSERVADO           = 'OBSERVADO';
    case INSCRITO            = 'INSCRITO';
    case ANULADO             = 'ANULADO';

    // Ciclo 2 - estados finales tras el calculo
    case ACEPTADO            = 'ACEPTADO';
    case SIN_CUPO            = 'SIN_CUPO';
}
