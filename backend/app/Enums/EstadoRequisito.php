<?php

namespace App\Enums;

enum EstadoRequisito: string
{
    case PENDIENTE = 'PENDIENTE';
    case VALIDADO  = 'VALIDADO';
    case OBSERVADO = 'OBSERVADO';
    case RECHAZADO = 'RECHAZADO';
}
