<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'docente_materias' (Ciclo 3 - CU12/CU13 mejorados)
| --------------------------------------------------------------------------
| Relacion N:N entre users (rol DOCENTE) y materias.
| Representa las materias que un docente esta HABILITADO para dar en el CUP.
|
| Uso:
|   - Al asignar un docente a un grupo (AsignacionDocente), el sistema
|     valida que el docente este habilitado en la materia del grupo.
|   - En el flujo guiado del formulario, solo se muestran los docentes
|     habilitados para la materia elegida.
|
| Regla: UNIQUE (docente_user_id, materia_id). Un docente no puede tener
| duplicada la misma materia en su perfil.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('docente_materias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('materia_id')
                ->constrained('materias')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['docente_user_id', 'materia_id'], 'docente_materias_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_materias');
    }
};
