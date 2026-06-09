<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Agrega campos de perfil a 'users'
| --------------------------------------------------------------------------
| Datos personales aplicables a TODOS los roles (admin, encargado, docente,
| coordinador/autoridad): fecha_nacimiento, ci y telefono.
| 'descripcion' es texto libre de perfil profesional, pensado para DOCENTE.
| El docente NO puede editar 'descripcion' (la mantiene el administrador).
| Todos los campos son nullable para no romper usuarios existentes.
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->nullable()->after('apellidos');
            $table->string('ci', 20)->nullable()->after('fecha_nacimiento');
            $table->string('telefono', 20)->nullable()->after('ci');
            $table->text('descripcion')->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fecha_nacimiento', 'ci', 'telefono', 'descripcion']);
        });
    }
};
