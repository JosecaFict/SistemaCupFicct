<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'fecha_nacimiento',
        'ci',
        'telefono',
        'descripcion',
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
            'fecha_nacimiento'  => 'date',
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

    /**
     * Materias que el docente esta HABILITADO para dar en el CUP.
     * Solo tiene sentido cuando el user tiene rol DOCENTE.
     * Se usa en AsignacionDocenteController::recursosDisponibles para
     * filtrar los docentes al asignar un grupo x materia.
     */
    public function materiasHabilitadas(): BelongsToMany
    {
        return $this->belongsToMany(
            Materia::class,
            'docente_materias',
            'docente_user_id',
            'materia_id'
        )->withTimestamps();
    }
}
