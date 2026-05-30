<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CupoCarrera extends Model
{
    protected $table = 'cupos_carrera';

    protected $fillable = ['gestion_cup_id', 'carrera_id', 'cupos'];

    protected $casts = ['cupos' => 'integer'];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(GestionCup::class, 'gestion_cup_id');
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }
}
