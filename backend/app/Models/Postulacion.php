<?php

namespace App\Models;

use App\Enums\EstadoPostulacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/*
| Modelo Postulacion
| --------------------------------------------------------------------------
| Vincula una persona con una gestion CUP, su carrera de primera y opcional
| segunda opcion. Es la entidad central del flujo (CU5..CU10).
*/
class Postulacion extends Model
{
    protected $table = 'postulaciones';

    protected $fillable = [
        'persona_id', 'gestion_cup_id', 'colegio_id', 'anio_egreso_colegio',
        'carrera_primera_id', 'carrera_segunda_id',
        'turno_id', 'grupo_id',
        'codigo_postulante', 'estado',
        'fecha_preinscripcion', 'fecha_inscripcion', 'fecha_anulacion',
        'observacion',
    ];

    protected $casts = [
        'estado'               => EstadoPostulacion::class,
        'fecha_preinscripcion' => 'datetime',
        'fecha_inscripcion'    => 'datetime',
        'fecha_anulacion'      => 'datetime',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(GestionCup::class, 'gestion_cup_id');
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class);
    }

    public function carreraPrimera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_primera_id');
    }

    public function carreraSegunda(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_segunda_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class)->orderBy('id', 'desc');
    }

    public function postulacionRequisitos(): HasMany
    {
        return $this->hasMany(PostulacionRequisito::class);
    }

    public function inscripcion(): HasOne
    {
        return $this->hasOne(Inscripcion::class);
    }

    /** True si existe AL MENOS un pago en estado APROBADO. */
    public function tienePagoAprobado(): bool
    {
        return $this->pagos()->where('estado', 'APROBADO')->exists();
    }
}
