<?php

namespace App\Services;

use App\Models\Postulacion;
use Illuminate\Support\Facades\DB;

/*
| CodigoPostulanteService
| --------------------------------------------------------------------------
| Genera el codigo unico de postulante (ej. '0000001') SOLO al confirmar
| inscripcion (CU10). No se genera en preinscripcion.
|
| Razon de hacerlo en Laravel (no en trigger PostgreSQL):
|   - La generacion ocurre como parte de una transaccion controlada en
|     InscripcionService::confirmar(), donde tambien se asigna grupo y se
|     incrementa el contador del grupo. Mantener todo en una sola transaccion
|     PHP simplifica el manejo de errores y rollback.
|   - El padding de ceros y la longitud son configurables por .env
|     (CODIGO_POSTULANTE_LENGTH) -- mas flexible que un trigger fijo.
|   - Si en el futuro se requiere reservar codigos o aplicar logica por
|     gestion, basta con extender este servicio.
|
| Concurrencia:
|   Usa SELECT ... FOR UPDATE sobre la fila MAX para serializar la generacion
|   de codigos cuando dos encargados confirman casi al mismo tiempo. La
|   transaccion externa debe estar abierta antes de llamar a generar().
*/
class CodigoPostulanteService
{
    /**
     * Genera el siguiente codigo unico. DEBE invocarse dentro de una transaccion.
     */
    public static function generar(): string
    {
        $longitud = (int) (env('CODIGO_POSTULANTE_LENGTH', 7));

        // Bloqueo de lectura para serializar (evita codigos duplicados bajo
        // concurrencia). PostgreSQL: usamos lockForUpdate sobre la tabla.
        $ultimoCodigo = Postulacion::query()
            ->whereNotNull('codigo_postulante')
            ->orderByDesc('codigo_postulante')
            ->lockForUpdate()
            ->value('codigo_postulante');

        $siguiente = $ultimoCodigo ? ((int) $ultimoCodigo) + 1 : 1;

        return str_pad((string) $siguiente, $longitud, '0', STR_PAD_LEFT);
    }
}
