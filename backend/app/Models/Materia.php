<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Materia extends Model
{
    protected $table = 'materias';

    protected $fillable = ['codigo', 'nombre', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    /**
     * Docentes habilitados para dar esta materia.
     * Lado inverso de User::materiasHabilitadas().
     */
    public function docentesHabilitados(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'docente_materias',
            'materia_id',
            'docente_user_id'
        )->withTimestamps();
    }
}
