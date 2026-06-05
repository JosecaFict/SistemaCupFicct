<?php

namespace App\Enums;

/*
| Enum OpcionAceptada (Ciclo 2)
| --------------------------------------------------------------------------
| Indica en cual opcion de carrera fue aceptado el postulante.
| Usado para la publicacion publica con formato:
|   "000001-1ra"  -> Aceptado en PRIMERA opcion
|   "000001-2da"  -> Aceptado en SEGUNDA opcion
|
| NINGUNA: el postulante no fue aceptado (SIN_CUPO).
*/
enum OpcionAceptada: string
{
    case PRIMERA = 'PRIMERA';
    case SEGUNDA = 'SEGUNDA';
    case NINGUNA = 'NINGUNA';
}
