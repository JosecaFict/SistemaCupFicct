<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Ciclo 2 - Agrega valor REPROBADO a estados finales
| --------------------------------------------------------------------------
| Antes solo habia ACEPTADO/SIN_CUPO/PENDIENTE_DESEMPATE en resultados y
| ACEPTADO/SIN_CUPO en postulaciones, pero SIN_CUPO englobaba 3 cosas:
|   1. Reprobados por nota minima en algun examen (descalificados)
|   2. Reprobados por promedio final menor a la nota minima
|   3. Aprobados que no entraron por falta de cupo en sus carreras
|
| Esta migracion separa el caso 1+2 como REPROBADO, dejando SIN_CUPO solo
| para el caso 3 (alumnos que aprobaron pero no tenian cupo).
|
| Estados validos despues de esta migracion:
|   - resultados.estado_final:    PENDIENTE_DESEMPATE, ACEPTADO, REPROBADO, SIN_CUPO
|   - postulaciones.estado:       (Ciclo 1) + ACEPTADO, REPROBADO, SIN_CUPO
*/
return new class extends Migration {
    public function up(): void
    {
        // resultados.estado_final
        DB::statement("ALTER TABLE resultados DROP CONSTRAINT IF EXISTS resultados_estado_final_check");
        DB::statement("
            ALTER TABLE resultados ADD CONSTRAINT resultados_estado_final_check
            CHECK (estado_final IN ('PENDIENTE_DESEMPATE', 'ACEPTADO', 'REPROBADO', 'SIN_CUPO'))
        ");

        // postulaciones.estado
        DB::statement("ALTER TABLE postulaciones DROP CONSTRAINT IF EXISTS postulaciones_estado_check");
        DB::statement("
            ALTER TABLE postulaciones ADD CONSTRAINT postulaciones_estado_check
            CHECK (estado IN (
                'PREINSCRITO','FORMULARIO_GENERADO','PAGO_APROBADO','OBSERVADO',
                'INSCRITO','ANULADO',
                'ACEPTADO','REPROBADO','SIN_CUPO'
            ))
        ");
    }

    public function down(): void
    {
        // Antes de revertir, normalizar datos: REPROBADO -> SIN_CUPO
        DB::statement("UPDATE resultados SET estado_final = 'SIN_CUPO' WHERE estado_final = 'REPROBADO'");
        DB::statement("UPDATE postulaciones SET estado = 'SIN_CUPO' WHERE estado = 'REPROBADO'");

        DB::statement("ALTER TABLE resultados DROP CONSTRAINT IF EXISTS resultados_estado_final_check");
        DB::statement("
            ALTER TABLE resultados ADD CONSTRAINT resultados_estado_final_check
            CHECK (estado_final IN ('PENDIENTE_DESEMPATE', 'ACEPTADO', 'SIN_CUPO'))
        ");

        DB::statement("ALTER TABLE postulaciones DROP CONSTRAINT IF EXISTS postulaciones_estado_check");
        DB::statement("
            ALTER TABLE postulaciones ADD CONSTRAINT postulaciones_estado_check
            CHECK (estado IN (
                'PREINSCRITO','FORMULARIO_GENERADO','PAGO_APROBADO','OBSERVADO',
                'INSCRITO','ANULADO','ACEPTADO','SIN_CUPO'
            ))
        ");
    }
};
