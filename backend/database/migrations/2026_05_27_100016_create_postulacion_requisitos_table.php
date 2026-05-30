<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'postulacion_requisitos'
| --------------------------------------------------------------------------
| Checklist por postulacion: por cada requisito del catalogo se registra si
| el postulante lo cumplio y quien lo verifico (CU8).
|
| Estados:
|   PENDIENTE   (todavia no se reviso)
|   VALIDADO    (encargado lo dio por bueno)
|   OBSERVADO   (encargado anoto algo, requiere correccion)
|   RECHAZADO   (no cumple)
|
| Para confirmar inscripcion (CU9), todos los requisitos OBLIGATORIOS deben
| estar en estado VALIDADO. Se verifica en InscripcionService.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('postulacion_requisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->cascadeOnDelete();
            $table->foreignId('requisito_id')->constrained('requisitos')->restrictOnDelete();
            $table->enum('estado', ['PENDIENTE', 'VALIDADO', 'OBSERVADO', 'RECHAZADO'])
                ->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->foreignId('verificado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verificado_at')->nullable();
            $table->timestamps();

            $table->unique(['postulacion_id', 'requisito_id'], 'uniq_postulacion_requisito');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulacion_requisitos');
    }
};
