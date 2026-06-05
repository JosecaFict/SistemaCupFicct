<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
| AmbienteSeeder
| --------------------------------------------------------------------------
| Crea 24 aulas distribuidas en 3 plantas (8 aulas por planta):
|   Planta 1: 11, 12, 13, 14, 15, 16, 17, 18
|   Planta 2: 21, 22, 23, 24, 25, 26, 27, 28
|   Planta 3: 31, 32, 33, 34, 35, 36, 37, 38
|
| Capacidad default 70 (configurable por gestion).
| Modalidad PRESENCIAL.
|
| Calculo de demanda: por turno hay maximo 5 grupos simultaneos
| (5 M, 5 T, 5 N son consecutivos en tiempo). 24 aulas cubren
| holgadamente esa demanda incluso si dos gestiones corren en paralelo.
*/
class AmbienteSeeder extends Seeder
{
    public function run(): void
    {
        $plantas = [
            1 => 'Planta 1',
            2 => 'Planta 2',
            3 => 'Planta 3',
        ];

        $now = now();
        $rows = [];

        foreach ($plantas as $planta => $ubicacion) {
            for ($n = 1; $n <= 8; $n++) {
                $codigo = $planta . $n; // 11, 12, ..., 18, 21, ..., 38
                $rows[] = [
                    'nombre'     => 'Aula ' . $codigo,
                    'modalidad'  => 'PRESENCIAL',
                    'ubicacion'  => $ubicacion,
                    'enlace'     => null,
                    'capacidad'  => 70,
                    'activo'     => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($rows as $r) {
            DB::table('ambientes')->updateOrInsert(
                ['nombre' => $r['nombre']],
                $r
            );
        }
    }
}
