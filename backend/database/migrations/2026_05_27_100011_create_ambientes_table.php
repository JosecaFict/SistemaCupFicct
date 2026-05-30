<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'ambientes'
| --------------------------------------------------------------------------
| Aulas o plataformas virtuales donde se dicta cada grupo CUP.
| Modalidad puede ser PRESENCIAL o VIRTUAL. Para VIRTUAL se usa 'enlace'
| (ej. URL de la plataforma de videoconferencia).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ambientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);                          // 'Aula 12', 'Sala Virtual A'
            $table->enum('modalidad', ['PRESENCIAL', 'VIRTUAL'])->default('PRESENCIAL');
            $table->string('ubicacion', 200)->nullable();
            $table->string('enlace', 300)->nullable();              // URL para modalidad VIRTUAL
            $table->unsignedSmallInteger('capacidad')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambientes');
    }
};
