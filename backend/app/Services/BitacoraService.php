<?php

namespace App\Services;

use App\Models\Bitacora;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/*
| BitacoraService
| --------------------------------------------------------------------------
| Inserta entradas en la tabla bitacora desde cualquier punto de la app.
| Eventos tipicos: LOGIN, LOGOUT, INSCRIPCION_CONFIRMADA, GRUPOS_GENERADOS,
| PAGO_APROBADO, REQUISITO_VERIFICADO.
*/
class BitacoraService
{
    public static function registrar(
        string $evento,
        ?string $entidad = null,
        ?int $entidadId = null,
        array $datos = []
    ): Bitacora {
        return Bitacora::create([
            'user_id'    => Auth::id(),
            'evento'     => $evento,
            'entidad'    => $entidad,
            'entidad_id' => $entidadId,
            'ip'         => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'datos'      => $datos,
        ]);
    }
}
