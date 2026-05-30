<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'bitacora'
| --------------------------------------------------------------------------
| Bitacora basica (Ciclo 1). Registra eventos relevantes para auditoria.
| Los eventos los inserta BitacoraService::registrar() desde los servicios
| (login, confirmacion de inscripcion, generacion de grupos, etc.).
|
| 'entidad' guarda el tipo afectado (ej. 'postulacion', 'pago', 'inscripcion').
| 'entidad_id' su PK. 'datos' JSON con detalles libres.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evento', 60);                       // 'LOGIN', 'INSCRIPCION_CONFIRMADA', etc.
            $table->string('entidad', 60)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('datos')->nullable();
            $table->timestamps();

            $table->index(['entidad', 'entidad_id']);
            $table->index('evento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
