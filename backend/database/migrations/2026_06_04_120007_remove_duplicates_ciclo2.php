<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Ciclo 2 - Correccion de duplicados
| --------------------------------------------------------------------------
| Las migraciones 120001 y 120002 agregaron columnas que duplican
| funcionalidad ya existente en el Ciclo 1:
|
|   gestiones_cup.numero_examenes       <-> ya existia cantidad_examenes
|   gestion_materias.valor_puntos       <-> ya existia ponderacion
|
| Esta migracion:
|   1. Elimina las columnas duplicadas que se agregaron en Ciclo 2.
|   2. Alinea el default de cantidad_examenes a 3 (segun documento
|      de la docente para el Sistema CUP FICCT).
|   3. Mantiene gestiones_cup.nota_minima_aprobacion (es genuinamente nueva).
|
| Nota: las columnas eliminadas estan vacias (no hay datos transaccionales
| que migrar), por eso es seguro hacerlo asi.
*/
return new class extends Migration {
    public function up(): void
    {
        // 1. Eliminar duplicados
        Schema::table('gestiones_cup', function (Blueprint $table) {
            $table->dropColumn('numero_examenes');
        });

        Schema::table('gestion_materias', function (Blueprint $table) {
            $table->dropColumn('valor_puntos');
        });

        // 2. Alinear default de cantidad_examenes (Ciclo 1 tenia default 2,
        //    pero el documento de la docente especifica 3).
        DB::statement("ALTER TABLE gestiones_cup ALTER COLUMN cantidad_examenes SET DEFAULT 3");
    }

    public function down(): void
    {
        // Restaurar default original de cantidad_examenes
        DB::statement("ALTER TABLE gestiones_cup ALTER COLUMN cantidad_examenes SET DEFAULT 2");

        // Restaurar las columnas duplicadas (revertir a estado post-120002)
        Schema::table('gestiones_cup', function (Blueprint $table) {
            $table->unsignedTinyInteger('numero_examenes')
                  ->default(3)
                  ->after('estado');
        });

        Schema::table('gestion_materias', function (Blueprint $table) {
            $table->decimal('valor_puntos', 5, 2)
                  ->default(25.00)
                  ->after('materia_id');
        });
    }
};
