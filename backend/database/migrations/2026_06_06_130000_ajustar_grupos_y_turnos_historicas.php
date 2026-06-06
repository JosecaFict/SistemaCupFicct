<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/*
| Migracion de DATOS (one-off): vuelve a ejecutar el comando idempotente
| cup:rebalancear-historicas, ya extendido para tambien:
|   - repartir los turnos al objetivo (134 M / 134 T / 132 N), y
|   - borrar los grupos que quedan vacios (M3..M5, T3..T5, N3..N5).
|
| Hace falta una migracion NUEVA porque la anterior (2026_06_06_120000) ya
| quedo registrada y no se vuelve a ejecutar. El comando es idempotente, asi
| que correrlo de nuevo es seguro. No altera el esquema; solo datos de las
| gestiones de demo 1-2025 y 2-2025. Si no existen, no hace nada.
*/
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('cup:rebalancear-historicas');
    }

    public function down(): void
    {
        // Migracion de datos no reversible: no se guarda el estado previo de
        // turno_id/grupo_id ni los grupos borrados. Para revertir se reseedea.
    }
};
