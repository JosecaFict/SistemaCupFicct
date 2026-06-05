<?php

namespace App\Models;

use App\Enums\EstadoResultado;
use App\Enums\OpcionAceptada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Modelo Resultado (Ciclo 2)
| --------------------------------------------------------------------------
| Resultado final del proceso de calculo por postulacion.
| Se genera (o actualiza si hay recalculo) cuando un COORD/ADMIN dispara
| el calculo de resultados de la gestion.
|
| Flujo de estado_final:
|   PENDIENTE_DESEMPATE -> ACEPTADO o SIN_CUPO  (resolucion manual del empate)
|
| Publicacion:
|   Despues del calculo, publicado=false. El COORD/ADMIN debe apretar
|   "Publicar resultados" para hacerlo visible al postulante publico.
|
| Vista publica:
|   Solo aparecen los ACEPTADOS con formato "codigo-1ra" o "codigo-2da".
|   Los SIN_CUPO no aparecen en la lista publica.
|
| Recalculo:
|   El ADMIN puede ejecutar recalculo en cualquier momento; al hacerlo,
|   esta fila se actualiza (UNIQUE postulacion_id).
*/
class Resultado extends Model
{
    protected $table = 'resultados';

    protected $fillable = [
        'postulacion_id',
        'nota_final',
        'ranking_global',
        'carrera_asignada_id',
        'opcion_aceptada',
        'estado_final',
        'motivo',
        'fecha_calculo',
        'calculado_por_user_id',
        'publicado',
        'fecha_publicacion',
        'publicado_por_user_id',
    ];

    protected $casts = [
        'nota_final'        => 'decimal:2',
        'ranking_global'    => 'integer',
        'opcion_aceptada'   => OpcionAceptada::class,
        'estado_final'      => EstadoResultado::class,
        'publicado'         => 'boolean',
        'fecha_calculo'     => 'datetime',
        'fecha_publicacion' => 'datetime',
    ];

    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function carreraAsignada(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_asignada_id');
    }

    public function calculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculado_por_user_id');
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por_user_id');
    }

    /**
     * Codigo publico con sufijo de opcion: "000001-1ra" o "000001-2da".
     * Solo tiene sentido si esta ACEPTADO.
     */
    public function getCodigoPublicoAttribute(): ?string
    {
        if ($this->estado_final !== EstadoResultado::ACEPTADO) {
            return null;
        }

        $sufijo = match ($this->opcion_aceptada) {
            OpcionAceptada::PRIMERA => '1ra',
            OpcionAceptada::SEGUNDA => '2da',
            default                 => null,
        };

        $codigo = $this->postulacion?->codigo_postulante;
        return ($codigo && $sufijo) ? "{$codigo}-{$sufijo}" : null;
    }

    public function fueAceptado(): bool
    {
        return $this->estado_final === EstadoResultado::ACEPTADO;
    }

    public function estaPublicado(): bool
    {
        return $this->publicado === true;
    }
}
