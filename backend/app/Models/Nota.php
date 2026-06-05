<?php

namespace App\Models;

use App\Enums\EstadoNota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Modelo Nota (Ciclo 2)
| --------------------------------------------------------------------------
| Una fila por (Postulacion x GestionMateria x numero_examen).
| El docente carga la nota desde la web (entrada directa, 0-100).
| Luego el COORDINADOR o ADMINISTRADOR la valida (por bloque).
|
| Flujo de estados:
|   PENDIENTE -> VALIDADA  (aprobada)
|              -> RECHAZADA (con observacion, vuelve a PENDIENTE al editar).
|
| Reglas (validadas en backend):
|   - valor entre 0 y 100 (CHECK en BD ademas).
|   - 0 = ausente o reprobado en ese examen.
|   - descalifica = true automatico cuando valor < nota_minima_aprobacion
|     de la gestion (regla: reprobar 1 examen descalifica al postulante).
|   - PENDIENTE: el docente puede modificar.
|   - VALIDADA: solo el ADMIN puede modificar (autoridad maxima).
*/
class Nota extends Model
{
    protected $table = 'notas';

    protected $fillable = [
        'postulacion_id',
        'gestion_materia_id',
        'numero_examen',
        'valor',
        'docente_user_id',
        'estado',
        'validado_por_user_id',
        'fecha_validacion',
        'observacion',
        'descalifica',
    ];

    protected $casts = [
        'valor'            => 'decimal:2',
        'numero_examen'    => 'integer',
        'estado'           => EstadoNota::class,
        'descalifica'      => 'boolean',
        'fecha_validacion' => 'datetime',
    ];

    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function gestionMateria(): BelongsTo
    {
        return $this->belongsTo(GestionMateria::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_user_id');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_user_id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoNota::PENDIENTE;
    }

    public function estaValidada(): bool
    {
        return $this->estado === EstadoNota::VALIDADA;
    }
}
