<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Modelo Persona
| --------------------------------------------------------------------------
| Datos personales del POSTULANTE (sin login). Tipo de documento:
|   CI_BO -> documento numerico + expedido (SC, LP, ...)
|   EXT   -> documento alfanumerico, sin expedido
*/
class Persona extends Model
{
    protected $table = 'personas';

    protected $fillable = [
        'tipo_documento', 'documento', 'expedido',
        'nombre', 'apellido_paterno', 'apellido_materno',
        'sexo', 'fecha_nacimiento',
        'email', 'telefono', 'direccion',
    ];

    protected $casts = ['fecha_nacimiento' => 'date'];

    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
    }
}
