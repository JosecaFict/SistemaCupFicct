<?php

namespace App\Enums;

enum EstadoPago: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADO  = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case CANCELADO = 'CANCELADO';
}
