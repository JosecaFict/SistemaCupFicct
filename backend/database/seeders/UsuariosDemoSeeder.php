<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/*
| UsuariosDemoSeeder (Ciclo 2)
| --------------------------------------------------------------------------
| Crea los usuarios demo necesarios para probar el sistema completo:
|   - 4 docentes (uno por materia: MAT, FIS, ING, COMP)
|   - 1 coordinador
|   - 1 encargado
|
| Todos con password "Demo2026*" para facilitar pruebas en defensa.
|
| Estos usuarios se referencian por los seeders de gestiones historicas
| (AsignacionesDemoSeeder, NotasDemoSeeder) para poblar datos realistas.
*/
class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $rolDocente     = Rol::where('codigo', 'DOCENTE')->firstOrFail();
        $rolCoordinador = Rol::where('codigo', 'COORDINADOR')->firstOrFail();
        $rolEncargado   = Rol::where('codigo', 'ENCARGADO')->firstOrFail();

        $passwordHash = Hash::make('Demo2026*');

        $usuarios = [
            // ----- Docentes (uno por materia) -----
            [
                'email'    => 'prof.matematica@cup-ficct.local',
                'nombre'   => 'Pedro',
                'apellidos'=> 'Mendoza Quispe',
                'role_id'  => $rolDocente->id,
            ],
            [
                'email'    => 'prof.fisica@cup-ficct.local',
                'nombre'   => 'Lucia',
                'apellidos'=> 'Vargas Roca',
                'role_id'  => $rolDocente->id,
            ],
            [
                'email'    => 'prof.ingles@cup-ficct.local',
                'nombre'   => 'Carlos',
                'apellidos'=> 'Lopez Mamani',
                'role_id'  => $rolDocente->id,
            ],
            [
                'email'    => 'prof.computacion@cup-ficct.local',
                'nombre'   => 'Sofia',
                'apellidos'=> 'Torres Choque',
                'role_id'  => $rolDocente->id,
            ],

            // ----- Coordinador -----
            [
                'email'    => 'coordinador@cup-ficct.local',
                'nombre'   => 'Ricardo',
                'apellidos'=> 'Salinas Bautista',
                'role_id'  => $rolCoordinador->id,
            ],

            // ----- Encargado -----
            [
                'email'    => 'encargado@cup-ficct.local',
                'nombre'   => 'Maria',
                'apellidos'=> 'Flores Cardenas',
                'role_id'  => $rolEncargado->id,
            ],
        ];

        foreach ($usuarios as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                array_merge($u, [
                    'password' => $passwordHash,
                    'activo'   => true,
                ])
            );
        }
    }
}
