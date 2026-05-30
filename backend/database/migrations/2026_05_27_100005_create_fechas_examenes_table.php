<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'fechas_examenes'
| --------------------------------------------------------------------------
| Fechas de los 2 o 3 examenes de una gestion CUP, con su ponderacion individual.
| Ejemplo:
|   numero=1, fecha=2026-02-10, ponderacion=50  -> Primer examen 50%
|   numero=2, fecha=2026-03-15, ponderacion=50  -> Segundo examen 50%
| La suma de ponderaciones debe dar 100 (validacion en Laravel).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fechas_examenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_cup_id')->constrained('gestiones_cup')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');                  // 1, 2, 3
            $table->date('fecha');
            $table->unsignedTinyInteger('ponderacion');             // 0-100 (%)
            $table->timestamps();

            $table->unique(['gestion_cup_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechas_examenes');
    }
};
