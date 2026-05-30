<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'grupos'
| --------------------------------------------------------------------------
| Grupos del CUP por gestion. Se generan AUTOMATICAMENTE (CU11) desde
| GrupoService::generarGruposParaGestion() usando la formula:
|
|   cantidad_grupos = CEIL(estimado_postulantes / capacidad_maxima_grupo)
|
| Los grupos se distribuyen entre los turnos habilitados de la gestion.
| Por ejemplo, si la gestion '1-2026' tiene 500 estimado / 40 capacidad => 13 grupos.
| Con turnos M,T,N habilitados: M1..M5, T1..T5, N1..N3.
|
| Campos:
|  - codigo: M1, M2, ... T1, ... N1, ... (unico por gestion).
|  - capacidad: copiada de la gestion al crearse (puede ajustarse despues).
|  - inscritos_actuales: contador denormalizado para evitar COUNT() en cada
|                        confirmacion de inscripcion (se incrementa en
|                        InscripcionService al confirmar).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_cup_id')->constrained('gestiones_cup')->cascadeOnDelete();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->foreignId('ambiente_id')->nullable()->constrained('ambientes')->nullOnDelete();
            $table->string('codigo', 5);                            // M1, T2, N3
            $table->unsignedSmallInteger('capacidad');
            $table->unsignedSmallInteger('inscritos_actuales')->default(0);
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();

            $table->unique(['gestion_cup_id', 'codigo'], 'uniq_grupo_codigo_gestion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
