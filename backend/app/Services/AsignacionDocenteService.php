<?php

namespace App\Services;

use App\Models\AsignacionDocente;
use Illuminate\Database\Eloquent\Builder;

/*
| AsignacionDocenteService
| --------------------------------------------------------------------------
| Logica reusable para asignaciones docente:
|   - Deteccion de choque de horarios (dias + horas).
|   - Filtrado de docentes / ambientes disponibles para una franja.
|   - Validacion antes de crear/editar una asignacion.
|
| Reglas:
|   1. Dos franjas chocan si comparten al menos un dia Y sus horas se
|      superponen (overlap).
|   2. El choque se evalua para:
|      - El docente (no puede estar en 2 lugares al mismo tiempo)
|      - El grupo (no puede tener 2 materias al mismo tiempo)
|      - El ambiente (no puede tener 2 clases al mismo tiempo)
*/
class AsignacionDocenteService
{
    /** Devuelve los dias como array a partir del CSV "LU,MI,VI". */
    public static function diasArray(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    /** True si los rangos horarios se superponen (excluyendo bordes). */
    public static function overlapHorario(string $a1, string $a2, string $b1, string $b2): bool
    {
        // No overlap si una franja termina antes o al inicio de la otra.
        if ($a2 <= $b1) return false;
        if ($a1 >= $b2) return false;
        return true;
    }

    /** True si dos franjas (dias + horas) se chocan. */
    public static function hayChoque(string $dias1, string $h1Ini, string $h1Fin, string $dias2, string $h2Ini, string $h2Fin): bool
    {
        $comunes = array_intersect(self::diasArray($dias1), self::diasArray($dias2));
        if (empty($comunes)) return false;
        return self::overlapHorario($h1Ini, $h1Fin, $h2Ini, $h2Fin);
    }

    /**
     * Devuelve los IDs de docentes que YA tienen un choque con la franja
     * (gestion + dias + horas). Excluye una asignacion (ignoreId) para casos
     * de edicion.
     */
    public static function docentesOcupados(int $gestionId, string $dias, string $horaIni, string $horaFin, ?int $ignoreId = null): array
    {
        $existentes = AsignacionDocente::where('gestion_cup_id', $gestionId)
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->get(['docente_user_id', 'dias_semana', 'hora_inicio', 'hora_fin']);

        $ocupados = [];
        foreach ($existentes as $e) {
            if (self::hayChoque($dias, $horaIni, $horaFin, $e->dias_semana, (string) $e->hora_inicio, (string) $e->hora_fin)) {
                $ocupados[$e->docente_user_id] = true;
            }
        }
        return array_keys($ocupados);
    }

    /** Lo mismo pero para ambientes (los que ya tienen una clase ahi). */
    public static function ambientesOcupados(int $gestionId, string $dias, string $horaIni, string $horaFin, ?int $ignoreId = null): array
    {
        $existentes = AsignacionDocente::where('gestion_cup_id', $gestionId)
            ->whereNotNull('ambiente_id')
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->get(['ambiente_id', 'dias_semana', 'hora_inicio', 'hora_fin']);

        $ocupados = [];
        foreach ($existentes as $e) {
            if (self::hayChoque($dias, $horaIni, $horaFin, $e->dias_semana, (string) $e->hora_inicio, (string) $e->hora_fin)) {
                $ocupados[$e->ambiente_id] = true;
            }
        }
        return array_keys($ocupados);
    }

    /** Lo mismo pero para grupos (los que ya tienen una clase a esa hora). */
    public static function gruposOcupados(int $gestionId, string $dias, string $horaIni, string $horaFin, ?int $ignoreId = null): array
    {
        $existentes = AsignacionDocente::where('gestion_cup_id', $gestionId)
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->get(['grupo_id', 'dias_semana', 'hora_inicio', 'hora_fin']);

        $ocupados = [];
        foreach ($existentes as $e) {
            if (self::hayChoque($dias, $horaIni, $horaFin, $e->dias_semana, (string) $e->hora_inicio, (string) $e->hora_fin)) {
                $ocupados[$e->grupo_id] = true;
            }
        }
        return array_keys($ocupados);
    }

    /**
     * Devuelve un array con los nombres de los conflictos detectados.
     * Vacio significa "OK, se puede asignar".
     */
    public static function detectarConflictos(int $gestionId, array $payload, ?int $ignoreId = null): array
    {
        $dias    = $payload['dias_semana'];
        $horaIni = $payload['hora_inicio'];
        $horaFin = $payload['hora_fin'];

        $conflictos = [];

        // Choque del docente
        if (in_array($payload['docente_user_id'], self::docentesOcupados($gestionId, $dias, $horaIni, $horaFin, $ignoreId), true)) {
            $conflictos[] = 'docente_ocupado';
        }

        // Choque del grupo
        if (in_array($payload['grupo_id'], self::gruposOcupados($gestionId, $dias, $horaIni, $horaFin, $ignoreId), true)) {
            $conflictos[] = 'grupo_ocupado';
        }

        // Choque del ambiente (solo si se especifico)
        if (!empty($payload['ambiente_id'])
            && in_array($payload['ambiente_id'], self::ambientesOcupados($gestionId, $dias, $horaIni, $horaFin, $ignoreId), true)) {
            $conflictos[] = 'ambiente_ocupado';
        }

        return $conflictos;
    }
}
