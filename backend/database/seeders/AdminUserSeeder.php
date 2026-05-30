<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/*
| AdminUserSeeder
| --------------------------------------------------------------------------
| Crea el usuario administrador por defecto. CAMBIA el password despues del
| primer login (la app no lo fuerza todavia; se hara en Ciclo 2 con
| "must_change_password").
|
| Credenciales por defecto:
|   email:    admin@cup-ficct.local
|   password: Admin123*
*/
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $rolAdmin = Rol::where('codigo', 'ADMINISTRADOR')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@cup-ficct.local'],
            [
                'role_id'   => $rolAdmin->id,
                'nombre'    => 'Administrador',
                'apellidos' => 'CUP FICCT',
                'password'  => Hash::make('Admin123*'),
                'activo'    => true,
            ]
        );
    }
}
