<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Ciclo 2 - Modifica 'gestion_materias'
| --------------------------------------------------------------------------
| Agrega 'valor_puntos': cuanto vale cada materia del total de 100 puntos
| que pondera el resultado final del CUP.
|
| Regla: la suma de valor_puntos de todas las materias de una gestion debe
| ser 100 (validacion a nivel de aplicacion).
|
| Default 25.00 (4 materias x 25 = 100, segun el documento de la docente).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::table('gestion_materias', function (Blueprint $table) {
            $table->decimal('valor_puntos', 5, 2)
                  ->default(25.00)
                  ->after('materia_id');
        });
    }

    public function down(): void
    {
        Schema::table('gestion_materias', function (Blueprint $table) {
            $table->dropColumn('valor_puntos');
        });
    }
};
