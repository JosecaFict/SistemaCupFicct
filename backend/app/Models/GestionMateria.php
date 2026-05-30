<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestionMateria extends Model
{
    protected $table = 'gestion_materias';

    protected $fillable = ['gestion_cup_id', 'materia_id', 'ponderacion'];

    protected $casts = ['ponderacion' => 'integer'];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(GestionCup::class, 'gestion_cup_id');
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }
}
