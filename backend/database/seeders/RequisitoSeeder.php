<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
| RequisitoSeeder
| --------------------------------------------------------------------------
| Catalogo de requisitos para CU8 (verificacion documental). Todos los del
| listado oficial son OBLIGATORIOS para confirmar la inscripcion.
*/
class RequisitoSeeder extends Seeder
{
    public function run(): void
    {
        $requisitos = [
            ['codigo' => 'FORMULARIO_PREINSC',   'nombre' => 'Formulario de preinscripcion', 'orden' => 1],
            ['codigo' => 'TITULO_BACHILLER',     'nombre' => 'Titulo de bachiller',          'orden' => 2],
            ['codigo' => 'FOTOCOPIA_TITULO',     'nombre' => 'Fotocopia titulo de bachiller','orden' => 3],
            ['codigo' => 'LIBRETA_ULTIMO',       'nombre' => 'Libreta de ultimo ano',        'orden' => 4],
            ['codigo' => 'FOTOCOPIA_LIBRETA',    'nombre' => 'Fotocopia libreta',            'orden' => 5],
            ['codigo' => 'CARNET',               'nombre' => 'Carnet / documento',           'orden' => 6],
            ['codigo' => 'FOTOCOPIA_CARNET',     'nombre' => 'Fotocopia carnet / documento', 'orden' => 7],
            ['codigo' => 'COMPROBANTE_PAGO',     'nombre' => 'Comprobante o confirmacion de pago', 'orden' => 8],
        ];

        foreach ($requisitos as $r) {
            DB::table('requisitos')->updateOrInsert(
                ['codigo' => $r['codigo']],
                array_merge($r, [
                    'obligatorio' => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ])
            );
        }
    }
}
