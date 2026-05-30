<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'inscripciones'
| --------------------------------------------------------------------------
| Registra el momento exacto en que el encargado CONFIRMA la inscripcion
| (CU9) y se genera codigo, turno y grupo (CU10).
|
| Reglas validadas en InscripcionService::confirmar():
|   1. La postulacion debe existir y no estar ANULADA ni INSCRITA.
|   2. Debe existir un pago APROBADO para esa postulacion.
|   3. Todos los requisitos obligatorios deben estar en VALIDADO.
|   4. El turno seleccionado debe tener al menos un grupo con cupo libre.
|   5. Se asigna el primer grupo ACTIVO de ese turno con
|      inscritos_actuales < capacidad.
|
| Una postulacion solo puede tener una inscripcion (unique).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->cascadeOnDelete()->unique();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->foreignId('confirmada_por_user_id')->constrained('users')->restrictOnDelete();
            $table->string('codigo_postulante', 12);                // espejo del que se guarda en postulaciones
            $table->timestamp('fecha_inscripcion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
