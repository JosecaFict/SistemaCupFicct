<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'colegios'
| --------------------------------------------------------------------------
| Catalogo simple de colegios. En Ciclo 1 se crea on-the-fly desde el
| formulario de preinscripcion (CU5) si el nombre no existe (firstOrCreate),
| para evitar pedir al postulante seleccionar de una lista enorme.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('colegios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('ciudad', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colegios');
    }
};
