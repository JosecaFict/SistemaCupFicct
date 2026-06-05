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
|   REPROBADO            Promedio final menor a la nota minima o
|                        descalificado por nota minima en algun examen.
|   SIN_CUPO             Aprobo el CUP pero las carreras que eligio
|                        estaban llenas (sin cupo real).
*/
enum EstadoResultado: string
{
    case PENDIENTE_DESEMPATE = 'PENDIENTE_DESEMPATE';
    case ACEPTADO            = 'ACEPTADO';
    case REPROBADO           = 'REPROBADO';
    case SIN_CUPO            = 'SIN_CUPO';
}
