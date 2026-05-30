<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
| RolSeeder
| --------------------------------------------------------------------------
| Crea los 4 roles del sistema. El postulante NO esta aqui porque no tiene
| cuenta de acceso. Los codigos se usan desde middleware 'role:CODIGO' y
| desde el frontend para decidir el sidebar.
*/
class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['codigo' => 'ADMINISTRADOR', 'nombre' => 'Administrador',           'descripcion' => 'Acceso total al sistema. Gestiona usuarios, gestion CUP y configuracion.'],
            ['codigo' => 'ENCARGADO',     'nombre' => 'Encargado de inscripcion','descripcion' => 'Verifica requisitos, confirma inscripcion y genera boleta.'],
            ['codigo' => 'DOCENTE',       'nombre' => 'Docente',                 'descripcion' => 'Registra calificaciones (modulo del Ciclo 2).'],
            ['codigo' => 'COORDINADOR',   'nombre' => 'Coordinador / Autoridad', 'descripcion' => 'Supervisa el proceso CUP y reportes (modulo del Ciclo 2).'],
        ];

        foreach ($roles as $rol) {
            DB::table('roles')->updateOrInsert(
                ['codigo' => $rol['codigo']],
                array_merge($rol, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
