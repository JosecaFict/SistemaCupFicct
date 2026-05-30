<?php

namespace App\Enums;

/*
| Enum TipoDocumento
| CI_BO -> Carnet de Identidad Bolivia (numerico + expedido)
| EXT   -> Extranjero (documento alfanumerico, sin expedido)
*/
enum TipoDocumento: string
{
    case CI_BO = 'CI_BO';
    case EXT   = 'EXT';
}
