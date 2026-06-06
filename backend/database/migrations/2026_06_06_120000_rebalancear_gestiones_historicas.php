<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/*
| Migracion de DATOS (one-off): re-balancea los grupos y aprueba los
| requisitos de las gestiones de demo 1-2025 y 2-2025, reutilizando el comando
| idempotente cup:rebalancear-historicas.
|
| No altera el esquema (no crea/borra/modifica tablas): solo reasigna grupo_id,
| recalcula inscritos_actuales y marca los requisitos como VALIDADO. El comando
| esta acotado SOLO a esas dos gestiones; si no existen (ej. base nueva o
| migrate:fresh antes de seedear), no hace nada.
|
| Se ejecuta sola al correr `php artisan migrate` (en el deploy o a mano).
*/
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('cup:rebalancear-historicas');
    }

    public function down(): void
    {
        // Migracion de datos no reversible: el re-balanceo no guarda el estado
        // previo de grupo_id. Para revertir se reseedearia la demo.
    }
};
