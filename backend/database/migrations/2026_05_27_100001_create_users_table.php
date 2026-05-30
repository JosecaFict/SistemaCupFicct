<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'users'
| --------------------------------------------------------------------------
| Usuarios con login del sistema CUP. Cada usuario tiene EXACTAMENTE UN rol
| (FK role_id -> roles.id). El postulante publico no se modela aqui porque
| no tiene cuenta de acceso (se modela en 'personas' + 'postulaciones').
|
| Campos importantes:
| - email unico (sirve de login y recuperacion).
| - activo: permite activar/inactivar usuarios sin borrarlos (CU2).
| - last_login_at: util para auditoria y dashboard del administrador.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('apellidos', 100);
            $table->string('email', 150)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Tablas que Laravel necesita para reset de password y sesiones
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
