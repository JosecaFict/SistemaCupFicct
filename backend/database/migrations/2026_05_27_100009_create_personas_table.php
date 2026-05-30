<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'personas'
| --------------------------------------------------------------------------
| Datos personales del POSTULANTE (no tiene login).
|
| Documento de identidad (CU4):
|  - tipo_documento: 'CI_BO' (CI Bolivia) o 'EXT' (extranjero/alfanumerico).
|  - documento: numero alfanumerico para extranjero, numerico para CI BO.
|  - expedido:  solo aplica para CI BO ('SC','LP','CB','OR','PT','TJ','CH','BN','PD').
|
| Validacion de unicidad: la pareja (tipo_documento, documento, expedido) es
| unica para evitar duplicados. Se materializa con un indice unico parcial.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_documento', ['CI_BO', 'EXT']);
            $table->string('documento', 30);
            $table->string('expedido', 2)->nullable();      // SC, LP, CB, ...
            $table->string('nombre', 100);
            $table->string('apellido_paterno', 100)->nullable();
            $table->string('apellido_materno', 100)->nullable();
            $table->enum('sexo', ['M', 'F', 'O'])->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion', 250)->nullable();
            $table->timestamps();

            // Indice unico compuesto (PostgreSQL admite NULLs como distintos, asi
            // que extranjeros con expedido NULL no se chocan entre si por aqui;
            // la unicidad real para extranjeros la cubre el indice de abajo).
            $table->unique(['tipo_documento', 'documento', 'expedido'], 'uniq_persona_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
