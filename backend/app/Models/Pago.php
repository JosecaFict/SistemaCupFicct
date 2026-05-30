<?php

namespace App\Models;

use App\Enums\EstadoPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'postulacion_id', 'monto', 'moneda', 'modo', 'estado',
        'stripe_payment_intent_id', 'stripe_session_id', 'stripe_client_secret',
        'referencia', 'payload', 'fecha_aprobacion',
    ];

    protected $casts = [
        'monto'            => 'decimal:2',
        'estado'           => EstadoPago::class,
        'payload'          => 'array',
        'fecha_aprobacion' => 'datetime',
    ];

    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(Postulacion::class);
    }
}
