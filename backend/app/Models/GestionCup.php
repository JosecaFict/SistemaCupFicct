<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Modelo GestionCup
| --------------------------------------------------------------------------
| Una "gestion" es un proceso CUP (ej. '1-2026'). Aqui se configuran fechas,
| capacidades, turnos habilitados y ponderaciones (CU3).
*/
class GestionCup extends Model
{
    protected $table = 'gestiones_cup';

    protected $fillable = [
        'codigo', 'nombre',
        'fecha_inicio_preinscripcion', 'fecha_cierre_preinscripcion',
        'cantidad_examenes', 'capacidad_maxima_grupo',
        'estimado_postulantes', 'turnos_habilitados', 'estado',
        // Ciclo 2
        'nota_minima_aprobacion',
    ];

    protected $casts = [
        'fecha_inicio_preinscripcion' => 'date',
        'fecha_cierre_preinscripcion' => 'date',
        'cantidad_examenes'           => 'integer',
        'capacidad_maxima_grupo'      => 'integer',
        'estimado_postulantes'        => 'integer',
        // Ciclo 2
        'nota_minima_aprobacion'      => 'decimal:2',
    ];

    public function fechasExamenes(): HasMany
    {
        return $this->hasMany(FechaExamen::class);
    }

    public function gestionMaterias(): HasMany
    {
        return $this->hasMany(GestionMateria::class);
    }

    public function cuposCarrera(): HasMany
    {
        return $this->hasMany(CupoCarrera::class);
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }

    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    // ----- Ciclo 2 -----

    public function asignacionesDocente(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class);
    }

    /** Turnos habilitados como array, ej. ['M','T','N']. */
    public function getTurnosArrayAttribute(): array
    {
        return array_filter(array_map('trim', explode(',', $this->turnos_habilitados ?? '')));
    }

    /**
     * Gestiones con la preinscripcion realmente ABIERTA: estado ACTIVA, con
     * codigo y fechas cargadas, y con HOY dentro del rango de preinscripcion.
     * Evita que una gestion fantasma/mal configurada habilite la preinscripcion.
     */
    public function scopeAbiertaPreinscripcion(Builder $q): Builder
    {
        $hoy = now()->toDateString();
        return $q->where('estado', 'ACTIVA')
            ->whereNotNull('codigo')
            ->whereNotNull('fecha_inicio_preinscripcion')
            ->whereNotNull('fecha_cierre_preinscripcion')
            ->whereDate('fecha_inicio_preinscripcion', '<=', $hoy)
            ->whereDate('fecha_cierre_preinscripcion', '>=', $hoy);
    }
}
