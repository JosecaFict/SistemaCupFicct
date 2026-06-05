<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Ciclo 2 - Tabla 'asignaciones_docente'
| --------------------------------------------------------------------------
| Asigna un docente (user con rol DOCENTE) a un (grupo x materia) con su
| horario detallado (dias + hora_inicio + hora_fin) y ambiente.
|
| Reglas (validadas en backend, no en BD):
|   - El docente no puede tener choque (mismos dias + overlap de horas).
|   - El grupo no puede tener 2 materias al mismo horario.
|   - El ambiente no puede tener 2 clases simultaneas.
|
| Patron de horarios (seeders demo):
|   Grupos IMPARES (M1, T1, N1, M3, T3, N3, ...):
|     L/Mi/V -> MAT y FIS
|     Ma/J/S -> ING y COMP
|   Grupos PARES (M2, T2, N2, M4, T4, N4, ...):
|     L/Mi/V -> ING y COMP
|     Ma/J/S -> MAT y FIS
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('asignaciones_docente', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gestion_cup_id')
                  ->constrained('gestiones_cup')
                  ->cascadeOnDelete();

            $table->foreignId('grupo_id')
                  ->constrained('grupos')
                  ->cascadeOnDelete();

            $table->foreignId('gestion_materia_id')
                  ->constrained('gestion_materias')
                  ->cascadeOnDelete();

            $table->foreignId('docente_user_id')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->foreignId('ambiente_id')
                  ->nullable()
                  ->constrained('ambientes')
                  ->nullOnDelete();

            // "LU,MI,VI" o "MA,JU,SA"
            $table->string('dias_semana', 30);
            $table->time('hora_inicio');
            $table->time('hora_fin');

            $table->timestamps();

            // Un grupo no puede tener la misma materia asignada 2 veces
            $table->unique(
                ['grupo_id', 'gestion_materia_id'],
                'uniq_grupo_materia_asignacion'
            );

            // Indices para queries de filtrado
            $table->index('docente_user_id');
            $table->index(['gestion_cup_id', 'docente_user_id']);
            $table->index(['ambiente_id', 'dias_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_docente');
    }
};
