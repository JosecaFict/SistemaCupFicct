<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'cupos_carrera'
| --------------------------------------------------------------------------
| Cantidad de cupos disponibles por carrera dentro de una gestion CUP.
| Se utiliza desde el modulo de resultados (Ciclo 5) para determinar quien
| queda ACEPTADO o SIN CUPO segun ranking de notas. Aqui ya queda guardado.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cupos_carrera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_cup_id')->constrained('gestiones_cup')->cascadeOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->unsignedSmallInteger('cupos');
            $table->timestamps();

            $table->unique(['gestion_cup_id', 'carrera_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupos_carrera');
    }
};
