<?php

namespace App\Enums;

/*
| Enum EstadoPostulacion -- Flujo Ciclo 1.
| PREINSCRITO -> FORMULARIO_GENERADO -> PAGO_APROBADO -> [OBSERVADO?] -> INSCRITO
| ANULADO puede llegar desde cualquier estado anterior a INSCRITO.
*/
enum EstadoPostulacion: string
{
    case PREINSCRITO         = 'PREINSCRITO';
    case FORMULARIO_GENERADO = 'FORMULARIO_GENERADO';
    case PAGO_APROBADO       = 'PAGO_APROBADO';
    case OBSERVADO           = 'OBSERVADO';
    case INSCRITO            = 'INSCRITO';
    case ANULADO             = 'ANULADO';
}
