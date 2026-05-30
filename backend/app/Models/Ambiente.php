<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ambiente extends Model
{
    protected $table = 'ambientes';

    protected $fillable = ['nombre', 'modalidad', 'ubicacion', 'enlace', 'capacidad', 'activo'];

    protected $casts = ['activo' => 'boolean', 'capacidad' => 'integer'];
}
