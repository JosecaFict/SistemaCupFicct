<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'gestion_materias'
| --------------------------------------------------------------------------
| Ponderacion de cada materia (MAT/FIS/ING/COMP) dentro de una gestion CUP.
| Permite que una gestion pondere distinto a otra sin tocar codigo.
| La suma de ponderaciones por gestion debe dar 100 (validacion Laravel).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('gestion_materias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_cup_id')->constrained('gestiones_cup')->cascadeOnDelete();
            $table->foreignId('materia_id')->constrained('materias')->restrictOnDelete();
            $table->unsignedTinyInteger('ponderacion');     // 0-100 (%)
            $table->timestamps();

            $table->unique(['gestion_cup_id', 'materia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestion_materias');
    }
};
