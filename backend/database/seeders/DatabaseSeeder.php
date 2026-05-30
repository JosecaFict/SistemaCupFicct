<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/*
| DatabaseSeeder
| --------------------------------------------------------------------------
| Punto de entrada de `php artisan db:seed`. Ejecuta los seeders del Ciclo 1
| en el orden correcto (catalogos -> usuario admin por defecto).
*/
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolSeeder::class,
            CarreraSeeder::class,
            MateriaSeeder::class,
            TurnoSeeder::class,
            RequisitoSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
