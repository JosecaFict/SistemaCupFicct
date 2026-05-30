<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    protected $table = 'carreras';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activa'];

    protected $casts = ['activa' => 'boolean'];
}
