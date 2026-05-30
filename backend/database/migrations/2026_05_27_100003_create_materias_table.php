<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'materias'
| --------------------------------------------------------------------------
| Catalogo de materias del CUP. Codigos fijos del Ciclo 1:
| MAT (Matematica), FIS (Fisica), ING (Ingles), COMP (Computacion).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 8)->unique();    // MAT, FIS, ING, COMP
            $table->string('nombre', 60);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};
