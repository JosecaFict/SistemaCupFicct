<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'bitacora';

    protected $fillable = [
        'user_id', 'evento', 'entidad', 'entidad_id',
        'ip', 'user_agent', 'datos',
    ];

    protected $casts = ['datos' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
