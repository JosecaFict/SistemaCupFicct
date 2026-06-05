<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Ciclo 2 - Modifica 'postulaciones.estado'
| --------------------------------------------------------------------------
| Agrega los valores ACEPTADO y SIN_CUPO al CHECK constraint del campo
| 'estado'. En Postgres, $table->enum() crea VARCHAR + CHECK, asi que
| modificamos el constraint para incluir los nuevos estados de Ciclo 2.
|
| Estados validos despues de Ciclo 2:
|   - PREINSCRITO, FORMULARIO_GENERADO, PAGO_APROBADO, OBSERVADO,
|     INSCRITO, ANULADO    (Ciclo 1)
|   - ACEPTADO, SIN_CUPO   (Ciclo 2, resultado final)
*/
return new class extends Migration {
    public function up(): void
    {
        // Drop el constraint viejo (si existe con ese nombre)
        DB::statement("ALTER TABLE postulaciones DROP CONSTRAINT IF EXISTS postulaciones_estado_check");

        // Agregar el nuevo constraint con los 8 estados
        DB::statement("
            ALTER TABLE postulaciones
            ADD CONSTRAINT postulaciones_estado_check
            CHECK (estado IN (
                'PREINSCRITO',
                'FORMULARIO_GENERADO',
                'PAGO_APROBADO',
                'OBSERVADO',
                'INSCRITO',
                'ANULADO',
                'ACEPTADO',
                'SIN_CUPO'
            ))
        ");
    }

    public function down(): void
    {
        // Revertir al constraint del Ciclo 1 (6 estados)
        DB::statement("ALTER TABLE postulaciones DROP CONSTRAINT IF EXISTS postulaciones_estado_check");

        DB::statement("
            ALTER TABLE postulaciones
            ADD CONSTRAINT postulaciones_estado_check
            CHECK (estado IN (
                'PREINSCRITO',
                'FORMULARIO_GENERADO',
                'PAGO_APROBADO',
                'OBSERVADO',
                'INSCRITO',
                'ANULADO'
            ))
        ");
    }
};
