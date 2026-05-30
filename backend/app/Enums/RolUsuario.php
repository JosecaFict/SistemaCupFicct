<?php

namespace App\Enums;

/*
| Enum RolUsuario -- Codigos de roles con login.
| Se usa desde middleware 'role:CODIGO' y desde policies.
*/
enum RolUsuario: string
{
    case ADMINISTRADOR = 'ADMINISTRADOR';
    case ENCARGADO     = 'ENCARGADO';
    case DOCENTE       = 'DOCENTE';
    case COORDINADOR   = 'COORDINADOR';
}
