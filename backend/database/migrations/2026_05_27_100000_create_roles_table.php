<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'roles'
| --------------------------------------------------------------------------
| Catalogo de roles con login. El postulante NO es un rol porque no tiene
| cuenta. Roles fijos del Ciclo 1: ADMINISTRADOR, ENCARGADO, DOCENTE, COORDINADOR.
| Relacion: roles 1 -- N users (cada usuario tiene exactamente un role_id).
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();   // ADMINISTRADOR, ENCARGADO, ...
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
