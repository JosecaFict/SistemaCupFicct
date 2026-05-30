<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'requisitos'
| --------------------------------------------------------------------------
| Catalogo de requisitos documentales que el postulante debe presentar (CU8).
| Se llenan con seeder. El flag 'obligatorio' decide si su ausencia bloquea
| la confirmacion de inscripcion (CU9).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('requisitos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();         // FORMULARIO_PREINSC, TITULO_BACHILLER, ...
            $table->string('nombre', 150);
            $table->boolean('obligatorio')->default(true);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos');
    }
};
