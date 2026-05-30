<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/*
| Modelo User
| --------------------------------------------------------------------------
| Usuario CON LOGIN. Cada user tiene exactamente un rol (role_id).
| El postulante publico NO se modela aqui (vease App\Models\Persona).
*/
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'nombre',
        'apellidos',
        'email',
        'password',
        'activo',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'role_id');
    }

    /**
     * Comprueba si el usuario tiene un rol determinado.
     * @param string $codigo Codigo del rol (ej. 'ADMINISTRADOR')
     */
    public function tieneRol(string $codigo): bool
    {
        return $this->rol?->codigo === $codigo;
    }

    /** Nombre completo (helper para UI / boleta). */
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellidos}");
    }
}
