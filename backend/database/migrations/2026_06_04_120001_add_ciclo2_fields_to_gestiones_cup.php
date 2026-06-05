<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Ciclo 2 - Modifica 'gestiones_cup'
| --------------------------------------------------------------------------
| Agrega los parametros academicos configurables por gestion:
|   - numero_examenes        Cantidad de examenes (2 o 3). Default 3 (segun
|                             requerimiento del documento de la docente).
|   - nota_minima_aprobacion  Nota minima del promedio final para considerar
|                             ACEPTADO. Default 60.00.
| Estos valores son editables por el ADMIN al crear o editar la gestion
| (Estrategia 3 con defaults del documento).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::table('gestiones_cup', function (Blueprint $table) {
            $table->unsignedTinyInteger('numero_examenes')
                  ->default(3)
                  ->after('estado');
            $table->decimal('nota_minima_aprobacion', 5, 2)
                  ->default(60.00)
                  ->after('numero_examenes');
        });
    }

    public function down(): void
    {
        Schema::table('gestiones_cup', function (Blueprint $table) {
            $table->dropColumn(['numero_examenes', 'nota_minima_aprobacion']);
        });
    }
};
