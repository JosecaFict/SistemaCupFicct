<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Modelo GestionMateria
| --------------------------------------------------------------------------
| Relaciona una materia (MAT/FIS/ING/COMP) con una gestion CUP. La columna
| 'ponderacion' (0-100) indica cuanto vale esa materia del total de 100
| puntos del CUP. La suma de ponderaciones por gestion debe dar 100
| (validacion a nivel de aplicacion).
|
| Ciclo 2 agrega relaciones a 'asignaciones_docente' y 'notas'.
*/
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

    // ----- Ciclo 2 -----

    public function asignacionesDocente(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }
}
