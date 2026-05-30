<?php

namespace App\Models;

use App\Enums\EstadoRequisito;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostulacionRequisito extends Model
{
    protected $table = 'postulacion_requisitos';

    protected $fillable = [
        'postulacion_id', 'requisito_id', 'estado', 'observacion',
        'verificado_por_user_id', 'verificado_at',
    ];

    protected $casts = [
        'estado'        => EstadoRequisito::class,
        'verificado_at' => 'datetime',
    ];

    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(Requisito::class);
    }

    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificado_por_user_id');
    }
}
