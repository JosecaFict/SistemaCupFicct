<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Requisito extends Model
{
    protected $table = 'requisitos';

    protected $fillable = ['codigo', 'nombre', 'obligatorio', 'orden'];

    protected $casts = ['obligatorio' => 'boolean', 'orden' => 'integer'];
}
