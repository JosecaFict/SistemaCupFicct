<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'carreras'
| --------------------------------------------------------------------------
| Catalogo de carreras ofertadas por la FICCT. Se llena via seeder.
| Carreras Ciclo 1: SIS (Ing. en Sistemas), INF (Ing. Informatica),
| RED (Ing. en Redes y Telecomunicaciones), ROB (Ing. Robotica).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('carreras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();    // SIS, INF, RED, ROB
            $table->string('nombre', 120);
            $table->string('descripcion', 300)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carreras');
    }
};
