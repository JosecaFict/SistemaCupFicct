<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'turnos'
| --------------------------------------------------------------------------
| Catalogo basico de turnos: Manana, Tarde, Noche.
| Cada turno tiene un prefijo de grupo:
|   M -> Manana   (M1, M2, M3, ...)
|   T -> Tarde    (T1, T2, T3, ...)
|   N -> Noche    (N1, N2, N3, ...)
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->unique();          // M, T, N
            $table->string('nombre', 30);                   // Manana, Tarde, Noche
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
