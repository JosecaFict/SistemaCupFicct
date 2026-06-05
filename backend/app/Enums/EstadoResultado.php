<?php

namespace App\Enums;

/*
| Enum EstadoResultado (Ciclo 2)
| --------------------------------------------------------------------------
| Estado final del proceso de calculo por postulante.
|
|   PENDIENTE_DESEMPATE  Hay empate de notas en el ultimo cupo y un
|                        COORDINADOR o ADMIN debe resolver manualmente.
|   ACEPTADO             Entro a una carrera (primera o segunda opcion).
|   SIN_CUPO             No entro (cupos llenos, nota insuficiente o
|                        descalificacion).
*/
enum EstadoResultado: string
{
    case PENDIENTE_DESEMPATE = 'PENDIENTE_DESEMPATE';
    case ACEPTADO            = 'ACEPTADO';
    case SIN_CUPO            = 'SIN_CUPO';
}
