<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
| MateriaSeeder -- Materias del CUP. Codigos fijos: MAT, FIS, ING, COMP.
*/
class MateriaSeeder extends Seeder
{
    public function run(): void
    {
        $materias = [
            ['codigo' => 'MAT',  'nombre' => 'Matematica'],
            ['codigo' => 'FIS',  'nombre' => 'Fisica'],
            ['codigo' => 'ING',  'nombre' => 'Ingles'],
            ['codigo' => 'COMP', 'nombre' => 'Computacion'],
        ];

        foreach ($materias as $m) {
            DB::table('materias')->updateOrInsert(
                ['codigo' => $m['codigo']],
                array_merge($m, [
                    'activa'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
