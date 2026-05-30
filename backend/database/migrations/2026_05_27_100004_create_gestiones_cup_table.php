<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'gestiones_cup'
| --------------------------------------------------------------------------
| Cada proceso CUP es una "gestion" (ej. '1-2026'). El administrador la crea
| desde CU3 y define:
|  - fechas de preinscripcion (apertura y cierre)
|  - cantidad de examenes (2 o 3) y sus fechas
|  - capacidad maxima por grupo (NO esta fija en codigo, viene de aqui)
|  - estimado de postulantes para calcular cuantos grupos crear
|  - turnos habilitados (CSV: M,T,N) que mas tarde se materializan en 'turnos'
|  - estado: BORRADOR | ACTIVA | CERRADA
|
| La ponderacion de materias y los cupos por carrera se guardan en tablas
| auxiliares (gestion_materias, cupos_carrera) para mantener integridad.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('gestiones_cup', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();    // '1-2026', '2-2026'
            $table->string('nombre', 120);
            $table->date('fecha_inicio_preinscripcion');
            $table->date('fecha_cierre_preinscripcion');
            $table->unsignedTinyInteger('cantidad_examenes')->default(2);   // 2 o 3
            $table->unsignedSmallInteger('capacidad_maxima_grupo');         // ej. 40
            $table->unsignedInteger('estimado_postulantes');                // ej. 500
            $table->string('turnos_habilitados', 10);                       // 'M,T,N'
            $table->enum('estado', ['BORRADOR', 'ACTIVA', 'CERRADA'])->default('BORRADOR');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestiones_cup');
    }
};
