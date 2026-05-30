<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    protected $table = 'materias';

    protected $fillable = ['codigo', 'nombre', 'activa'];

    protected $casts = ['activa' => 'boolean'];
}
