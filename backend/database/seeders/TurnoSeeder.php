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
        // Ciclo 2 (2026-06-04): horarios ajustados al patron del CUP
        // (2 materias por turno por dia, bloques de 2 hs).
        $turnos = [
            ['codigo' => 'M', 'nombre' => 'Manana', 'hora_inicio' => '08:00', 'hora_fin' => '12:00'],
            ['codigo' => 'T', 'nombre' => 'Tarde',  'hora_inicio' => '13:00', 'hora_fin' => '17:00'],
            ['codigo' => 'N', 'nombre' => 'Noche',  'hora_inicio' => '18:00', 'hora_fin' => '22:00'],
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
