<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FechaExamen extends Model
{
    protected $table = 'fechas_examenes';

    protected $fillable = ['gestion_cup_id', 'numero', 'fecha', 'ponderacion'];

    protected $casts = ['fecha' => 'date', 'ponderacion' => 'integer'];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(GestionCup::class, 'gestion_cup_id');
    }
}
