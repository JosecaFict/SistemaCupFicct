<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Ciclo 2 - Tabla 'resultados'
| --------------------------------------------------------------------------
| Guarda el resultado final del proceso de calculo para cada postulacion.
| Se calcula cuando todas las notas estan VALIDADAS y un COORD o ADMIN
| dispara el calculo.
|
| Flujo de estado_final:
|   PENDIENTE_DESEMPATE  ->  ACEPTADO  o  SIN_CUPO
|       (cuando hay empate de notas en el ultimo cupo, queda pausado
|        hasta que el COORD/ADMIN resuelve manualmente).
|
| Publicacion:
|   Despues de calcular, queda publicado=false. El COORD/ADMIN debe
|   apretar "Publicar" para hacer visible al postulante publico.
|   Solo los ACEPTADOS aparecen en la lista publica como:
|       "codigo_postulante-1ra"  (si fue aceptado en su 1ra opcion)
|       "codigo_postulante-2da"  (si fue aceptado en su 2da opcion)
|
| Recalculo: el ADMIN puede ejecutar recalculo en cualquier momento.
| Al recalcular, se actualizan estas filas (UNIQUE postulacion_id).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('resultados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('postulacion_id')
                  ->unique()
                  ->constrained('postulaciones')
                  ->cascadeOnDelete();

            $table->decimal('nota_final', 5, 2);
            $table->integer('ranking_global')->nullable();

            $table->foreignId('carrera_asignada_id')
                  ->nullable()
                  ->constrained('carreras')
                  ->nullOnDelete();

            // PRIMERA, SEGUNDA, NINGUNA
            $table->string('opcion_aceptada', 10)->nullable();

            // PENDIENTE_DESEMPATE, ACEPTADO, SIN_CUPO
            $table->string('estado_final', 30);

            $table->text('motivo')->nullable();

            $table->timestamp('fecha_calculo');

            $table->foreignId('calculado_por_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Publicacion (paso separado del calculo)
            $table->boolean('publicado')->default(false);
            $table->timestamp('fecha_publicacion')->nullable();

            $table->foreignId('publicado_por_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index('estado_final');
            $table->index('publicado');
            $table->index('carrera_asignada_id');
        });

        // CHECK constraints
        DB::statement("
            ALTER TABLE resultados
            ADD CONSTRAINT resultados_opcion_check
            CHECK (opcion_aceptada IS NULL OR opcion_aceptada IN ('PRIMERA', 'SEGUNDA', 'NINGUNA'))
        ");

        DB::statement("
            ALTER TABLE resultados
            ADD CONSTRAINT resultados_estado_final_check
            CHECK (estado_final IN ('PENDIENTE_DESEMPATE', 'ACEPTADO', 'SIN_CUPO'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados');
    }
};
