<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    protected $table = 'turnos';

    protected $fillable = ['codigo', 'nombre', 'hora_inicio', 'hora_fin', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }
}
