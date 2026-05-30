<?php

namespace App\Services;

use App\Models\GestionCup;
use App\Models\Grupo;
use App\Models\Turno;
use Illuminate\Support\Facades\DB;

/*
| GrupoService
| --------------------------------------------------------------------------
| Logica de generacion automatica de grupos para una gestion CUP (CU11).
|
| Formula:
|   cantidad_grupos = CEIL(estimado_postulantes / capacidad_maxima_grupo)
|
| Distribucion entre turnos habilitados (round-robin):
|   Si la gestion habilita M,T,N y se requieren 13 grupos -> se reparten asi:
|     M1, T1, N1, M2, T2, N2, M3, T3, N3, M4, T4, N4, M5
|
|   Resultado: cada turno recibe ceil(13/3)=5 maximo. La numeracion crece
|   por turno: M1..M5, T1..T4, N1..N4.
|
| Por que en Laravel (no trigger):
|   - Requiere operaciones sobre catalogo (turnos), aritmetica condicional
|     y feedback al usuario sobre cuantos grupos se crearon por turno.
|   - Triggers en PostgreSQL son ideales para invariantes simples; este
|     proceso amerita test unitario y reuso desde un endpoint admin.
*/
class GrupoService
{
    /**
     * Genera (o regenera) los grupos para la gestion dada.
     * Devuelve un resumen [turno_codigo => cantidad_generada].
     */
    public static function generarParaGestion(GestionCup $gestion, bool $forzar = false): array
    {
        return DB::transaction(function () use ($gestion, $forzar) {
            // Si ya existen grupos para esta gestion y no se pidio forzar, abortar.
            $existen = Grupo::where('gestion_cup_id', $gestion->id)->exists();
            if ($existen && !$forzar) {
                throw new \DomainException('La gestion ya tiene grupos. Use forzar=true para regenerar.');
            }

            // Si se fuerza, borrar grupos sin inscritos (no tocamos los que ya tienen gente).
            if ($existen && $forzar) {
                Grupo::where('gestion_cup_id', $gestion->id)
                    ->where('inscritos_actuales', 0)
                    ->delete();
            }

            // Total a generar
            $cantidad = (int) ceil(
                max(0, $gestion->estimado_postulantes) / max(1, $gestion->capacidad_maxima_grupo)
            );

            // Turnos habilitados de la gestion, ej. ['M','T','N']
            $codigosTurnos = $gestion->turnos_array;
            if (empty($codigosTurnos)) {
                throw new \DomainException('La gestion no tiene turnos habilitados.');
            }

            $turnos = Turno::whereIn('codigo', $codigosTurnos)->where('activo', true)->get()->keyBy('codigo');
            if ($turnos->isEmpty()) {
                throw new \DomainException('Ningun turno habilitado esta activo en el catalogo.');
            }

            // Numerador por turno (a partir de los grupos ya existentes)
            $contadores = [];
            foreach ($turnos as $codigo => $turno) {
                $contadores[$codigo] = (int) Grupo::where('gestion_cup_id', $gestion->id)
                    ->where('turno_id', $turno->id)
                    ->count();
            }

            $resumen = array_fill_keys(array_keys($contadores), 0);

            // Asignacion round-robin: turnos en el orden indicado por la gestion
            $i = 0;
            $turnosOrdenados = [];
            foreach ($codigosTurnos as $cod) {
                if (isset($turnos[$cod])) {
                    $turnosOrdenados[] = $turnos[$cod];
                }
            }
            $cantTurnos = count($turnosOrdenados);

            while ($i < $cantidad) {
                $turno = $turnosOrdenados[$i % $cantTurnos];
                $contadores[$turno->codigo]++;
                $codigoGrupo = $turno->codigo . $contadores[$turno->codigo];

                Grupo::create([
                    'gestion_cup_id'     => $gestion->id,
                    'turno_id'           => $turno->id,
                    'codigo'             => $codigoGrupo,
                    'capacidad'          => $gestion->capacidad_maxima_grupo,
                    'inscritos_actuales' => 0,
                    'estado'             => 'ACTIVO',
                ]);

                $resumen[$turno->codigo]++;
                $i++;
            }

            BitacoraService::registrar(
                evento: 'GRUPOS_GENERADOS',
                entidad: 'gestion_cup',
                entidadId: $gestion->id,
                datos: ['cantidad' => $cantidad, 'resumen' => $resumen, 'forzar' => $forzar]
            );

            return $resumen;
        });
    }
}
