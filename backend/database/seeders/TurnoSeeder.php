<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
| TurnoSeeder -- Manana, Tarde, Noche. Los grupos se nombran M1, T1, N1...
*/
class TurnoSeeder extends Seeder
{
    public function run(): void
    {
        $turnos = [
            ['codigo' => 'M', 'nombre' => 'Manana', 'hora_inicio' => '07:00', 'hora_fin' => '11:30'],
            ['codigo' => 'T', 'nombre' => 'Tarde',  'hora_inicio' => '14:00', 'hora_fin' => '18:30'],
            ['codigo' => 'N', 'nombre' => 'Noche',  'hora_inicio' => '19:00', 'hora_fin' => '22:30'],
        ];

        foreach ($turnos as $t) {
            DB::table('turnos')->updateOrInsert(
                ['codigo' => $t['codigo']],
                array_merge($t, [
                    'activo'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
