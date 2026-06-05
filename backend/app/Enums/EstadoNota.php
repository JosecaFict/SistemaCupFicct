<?php

namespace App\Enums;

/*
| Enum EstadoNota (Ciclo 2)
| --------------------------------------------------------------------------
| Flujo:
|   PENDIENTE   ->  VALIDADA    (aprobada por COORDINADOR o ADMINISTRADOR)
|                ->  RECHAZADA   (con observacion; el docente corrige y vuelve
|                                 a PENDIENTE).
*/
enum EstadoNota: string
{
    case PENDIENTE = 'PENDIENTE';
    case VALIDADA  = 'VALIDADA';
    case RECHAZADA = 'RECHAZADA';
}
