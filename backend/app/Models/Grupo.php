<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Modelo Grupo
| --------------------------------------------------------------------------
| Grupo de una gestion CUP en un turno. Su 'codigo' sigue el patron
| <prefijo_turno><n>, ej. M1, T2, N3. El contador 'inscritos_actuales' se
| actualiza desde InscripcionService al confirmar una inscripcion.
*/
class Grupo extends Model
{
    protected $table = 'grupos';

    protected $fillable = [
        'gestion_cup_id', 'turno_id', 'ambiente_id',
        'codigo', 'capacidad', 'inscritos_actuales', 'estado',
    ];

    protected $casts = [
        'capacidad'          => 'integer',
        'inscritos_actuales' => 'integer',
    ];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(GestionCup::class, 'gestion_cup_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function ambiente(): BelongsTo
    {
        return $this->belongsTo(Ambiente::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /** True si el grupo tiene al menos 1 cupo libre. */
    public function tieneCupo(): bool
    {
        return $this->estado === 'ACTIVO' && $this->inscritos_actuales < $this->capacidad;
    }

    public function getCuposLibresAttribute(): int
    {
        return max(0, $this->capacidad - $this->inscritos_actuales);
    }
}
