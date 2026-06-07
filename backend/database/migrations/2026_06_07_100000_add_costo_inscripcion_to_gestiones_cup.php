<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Agrega el costo de inscripcion configurable por gestion (CU7 / pago).
| Default 700.00 BOB. Las gestiones existentes quedan en 700 por el default.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::table('gestiones_cup', function (Blueprint $table) {
            $table->decimal('costo_inscripcion', 8, 2)->default(700.00)->after('nota_minima_aprobacion');
        });
    }

    public function down(): void
    {
        Schema::table('gestiones_cup', function (Blueprint $table) {
            $table->dropColumn('costo_inscripcion');
        });
    }
};
