<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Ciclo 2 - Tabla 'notas'
| --------------------------------------------------------------------------
| Una fila por (postulante x materia x examen). El docente carga estas notas
| desde la web (entrada directa, sin Excel) y luego son validadas por el
| COORDINADOR o ADMIN (validacion por bloque).
|
| Flujo de estados:
|   PENDIENTE  ->  VALIDADA   (aprobada por COORD/ADMIN)
|               ->  RECHAZADA  (con observacion; el docente debe corregir)
|
| Reglas:
|   - valor entre 0 y 100 (CHECK constraint).
|   - 0 = ausente o reprobado en el examen.
|   - 'descalifica' se setea TRUE automaticamente cuando valor < nota_minima
|     de la gestion (regla del usuario: reprobar 1 examen ya descalifica).
|   - Mientras este en PENDIENTE, el docente puede modificar.
|   - VALIDADA solo el ADMIN puede modificar (autoridad maxima).
|   - RECHAZADA vuelve a PENDIENTE al editar.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('postulacion_id')
                  ->constrained('postulaciones')
                  ->cascadeOnDelete();

            $table->foreignId('gestion_materia_id')
                  ->constrained('gestion_materias')
                  ->restrictOnDelete();

            $table->unsignedTinyInteger('numero_examen'); // 1, 2 o 3

            $table->decimal('valor', 5, 2); // 0.00 - 100.00

            $table->foreignId('docente_user_id')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->string('estado', 20)->default('PENDIENTE');

            $table->foreignId('validado_por_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('fecha_validacion')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('descalifica')->default(false);

            $table->timestamps();

            // No puede haber 2 notas para el mismo (postulante, materia, examen)
            $table->unique(
                ['postulacion_id', 'gestion_materia_id', 'numero_examen'],
                'uniq_nota_postulacion_materia_examen'
            );

            $table->index('estado');
            $table->index(['gestion_materia_id', 'numero_examen']);
            $table->index('docente_user_id');
        });

        // CHECK constraints adicionales (rango de valor y enum de estado)
        DB::statement("
            ALTER TABLE notas
            ADD CONSTRAINT notas_valor_range CHECK (valor >= 0 AND valor <= 100)
        ");

        DB::statement("
            ALTER TABLE notas
            ADD CONSTRAINT notas_estado_check
            CHECK (estado IN ('PENDIENTE', 'VALIDADA', 'RECHAZADA'))
        ");

        DB::statement("
            ALTER TABLE notas
            ADD CONSTRAINT notas_numero_examen_check
            CHECK (numero_examen BETWEEN 1 AND 3)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};
