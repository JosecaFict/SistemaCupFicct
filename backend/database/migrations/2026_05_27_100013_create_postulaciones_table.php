<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'postulaciones'
| --------------------------------------------------------------------------
| Una postulacion es el vinculo persona -> gestion CUP con sus opciones de
| carrera. Es la entidad central del proceso.
|
| Flujo de estados (Ciclo 1):
|   PREINSCRITO          (al crear preinscripcion publica)
|   -> FORMULARIO_GENERADO  (al generar el formulario PDF)
|   -> PAGO_APROBADO        (cuando el pago llega a APROBADO)
|   -> OBSERVADO            (si encargado marca observaciones en requisitos)
|   -> INSCRITO             (al confirmar inscripcion + asignar grupo)
|   -> ANULADO              (admin/encargado lo anula manualmente)
|
| Campo 'codigo_postulante': se genera SOLO al confirmar inscripcion (CU10).
| Antes de eso permanece NULL. Indice unico parcial: dos postulaciones no
| pueden tener el mismo codigo (NULLs no chocan en PostgreSQL).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('postulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('gestion_cup_id')->constrained('gestiones_cup')->restrictOnDelete();
            $table->foreignId('colegio_id')->nullable()->constrained('colegios')->nullOnDelete();
            $table->unsignedSmallInteger('anio_egreso_colegio')->nullable();

            // Opciones de carrera del postulante
            $table->foreignId('carrera_primera_id')->constrained('carreras')->restrictOnDelete();
            $table->foreignId('carrera_segunda_id')->nullable()->constrained('carreras')->nullOnDelete();

            // Asignacion (se llenan al confirmar inscripcion)
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->nullOnDelete();
            $table->string('codigo_postulante', 12)->nullable();    // ej. '0000001'

            // Estado actual de la postulacion
            $table->enum('estado', [
                'PREINSCRITO',
                'FORMULARIO_GENERADO',
                'PAGO_APROBADO',
                'OBSERVADO',
                'INSCRITO',
                'ANULADO',
            ])->default('PREINSCRITO');

            $table->timestamp('fecha_preinscripcion')->nullable();
            $table->timestamp('fecha_inscripcion')->nullable();
            $table->timestamp('fecha_anulacion')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['persona_id', 'gestion_cup_id'], 'uniq_postulacion_persona_gestion');
            $table->index('codigo_postulante');
        });

        // Indice unico parcial para 'codigo_postulante' cuando no es NULL.
        // (Asegura que dos inscritos no tengan el mismo codigo, pero permite
        //  multiples postulaciones sin codigo asignado todavia.)
        // PostgreSQL syntax:
        \Illuminate\Support\Facades\DB::statement(
            "CREATE UNIQUE INDEX uniq_codigo_postulante_not_null
             ON postulaciones (codigo_postulante)
             WHERE codigo_postulante IS NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('postulaciones');
    }
};
