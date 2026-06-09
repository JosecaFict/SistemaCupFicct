<?php

namespace App\Models;

use App\Services\AsignacionDocenteService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Modelo AsignacionDocente (Ciclo 2)
| --------------------------------------------------------------------------
| Vincula un docente (User con rol DOCENTE) a un (Grupo x Materia) con
| horario detallado (dias_semana, hora_inicio, hora_fin) y ambiente.
|
| Reglas (validadas en backend, no en BD):
|   - El docente no puede tener choque (mismos dias + overlap horario).
|   - El grupo no puede tener 2 materias al mismo horario.
|   - El ambiente no puede tener 2 clases simultaneas.
|
| dias_semana es CSV: "LU,MI,VI" o "MA,JU,SA".
*/
class AsignacionDocente extends Model
{
    protected $table = 'asignaciones_docente';

    protected $fillable = [
        'gestion_cup_id',
        'grupo_id',
        'gestion_materia_id',
        'docente_user_id',
        'ambiente_id',
        'dias_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i:s',
        'hora_fin'    => 'datetime:H:i:s',
    ];

    /*
    | Validacion defensiva en el modelo
    | --------------------------------------------------------------------------
    | Re-ejecuta AsignacionDocenteService::detectarConflictos antes de cada
    | INSERT/UPDATE. El controller ya valida en store/update, pero esto cubre
    | seeders, factories, Tinker o scripts que usen el modelo Eloquent y
    | salten la validacion del controller. NO cubre DB::table()->insert()
    | porque ese bypassea los eventos del modelo (decision intencional para
    | no encarecer seeders historicos que ya generan datos validos).
    */
    protected static function booted(): void
    {
        static::saving(function (self $a) {
            $payload = [
                'grupo_id'        => $a->grupo_id,
                'docente_user_id' => $a->docente_user_id,
                'ambiente_id'     => $a->ambiente_id,
                'dias_semana'     => $a->dias_semana,
                'hora_inicio'     => self::formatHoraParaValidacion($a->hora_inicio),
                'hora_fin'        => self::formatHoraParaValidacion($a->hora_fin),
            ];
            $conflictos = AsignacionDocenteService::detectarConflictos(
                (int) $a->gestion_cup_id,
                $payload,
                $a->exists ? $a->id : null
            );
            if (!empty($conflictos)) {
                throw new DomainException(
                    'Asignacion docente invalida (' . implode(', ', $conflictos) . ').'
                );
            }
        });
    }

    /** Normaliza la hora a "HH:MM" sea Carbon, string o time DB. */
    private static function formatHoraParaValidacion(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('H:i');
        }
        $s = (string) ($valor ?? '');
        // Extrae el primer "HH:MM" del string (sirve para "08:00", "08:00:00"
        // y "2026-01-01 08:00:00").
        if (preg_match('/(\d{2}:\d{2})/', $s, $m)) {
            return $m[1];
        }
        return $s;
    }

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(GestionCup::class, 'gestion_cup_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function gestionMateria(): BelongsTo
    {
        return $this->belongsTo(GestionMateria::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_user_id');
    }

    public function ambiente(): BelongsTo
    {
        return $this->belongsTo(Ambiente::class);
    }

    /** Devuelve los dias como array: ['LU','MI','VI']. */
    public function getDiasArrayAttribute(): array
    {
        return array_filter(array_map('trim', explode(',', $this->dias_semana ?? '')));
    }
}
