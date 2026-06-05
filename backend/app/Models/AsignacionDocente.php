<?php

namespace App\Models;

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
