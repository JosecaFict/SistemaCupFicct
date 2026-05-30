<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
| CarreraSeeder -- Carreras ofertadas por la FICCT.
*/
class CarreraSeeder extends Seeder
{
    public function run(): void
    {
        $carreras = [
            ['codigo' => 'SIS', 'nombre' => 'Ingenieria en Sistemas'],
            ['codigo' => 'INF', 'nombre' => 'Ingenieria Informatica'],
            ['codigo' => 'RED', 'nombre' => 'Ingenieria en Redes y Telecomunicaciones'],
            ['codigo' => 'ROB', 'nombre' => 'Ingenieria Robotica'],
        ];

        foreach ($carreras as $c) {
            DB::table('carreras')->updateOrInsert(
                ['codigo' => $c['codigo']],
                array_merge($c, [
                    'activa'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
