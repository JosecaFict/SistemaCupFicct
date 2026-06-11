<?php

namespace Database\Seeders;

use App\Enums\EstadoPostulacion;
use App\Enums\EstadoRequisito;
use App\Models\Postulacion;
use App\Models\PostulacionRequisito;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\User;
use App\Services\InscripcionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/*
| ValidarInscripciones2026Seeder
| --------------------------------------------------------------------------
| Valida (requisitos VALIDADO) y confirma la inscripcion de N postulantes
| pendientes (PAGO_APROBADO) de la gestion 1-2026 al turno indicado.
|
| Replica lo que haria el encargado a mano (CU8 + CU9):
|   - marca todos los requisitos como VALIDADO
|   - InscripcionService::confirmar(turno) -> asigna grupo con cupo, genera
|     codigo y pasa la postulacion a INSCRITO.
|
| El grupo lo elige el sistema: primer grupo ACTIVO del turno con cupo libre
| (ordenado por codigo). Para 32 al turno M con M1 (33 libres) -> todos a M1.
*/
class ValidarInscripciones2026Seeder extends Seeder
{
    private const GESTION_ID   = 3;
    private const TURNO_CODIGO = 'M';
    private const CANTIDAD     = 32;

    public function run(): void
    {
        // Usuario admin para "confirmado por" + bitacora.
        $rolAdminId = Rol::where('codigo', 'ADMINISTRADOR')->value('id');
        $admin = User::where('role_id', $rolAdminId)->where('activo', true)->first();
        if (!$admin) {
            $this->command->error('No hay usuario ADMINISTRADOR activo.');
            return;
        }
        Auth::setUser($admin);
        $this->command->info("Actuando como admin: {$admin->email}");

        $turno = Turno::where('codigo', self::TURNO_CODIGO)->first();
        if (!$turno) {
            $this->command->error('Turno no encontrado: ' . self::TURNO_CODIGO);
            return;
        }
        $this->command->info("Turno destino: [{$turno->id}] {$turno->codigo} - {$turno->nombre}");

        $pendientes = Postulacion::where('gestion_cup_id', self::GESTION_ID)
            ->where('estado', EstadoPostulacion::PAGO_APROBADO->value)
            ->orderBy('id')
            ->limit(self::CANTIDAD)
            ->get();

        $this->command->info('Pendientes tomados (primeros por orden): ' . $pendientes->count());

        $ok = 0;
        $fail = 0;
        $grupos = [];

        foreach ($pendientes as $p) {
            try {
                // (CU8) Marcar todos los requisitos como VALIDADO.
                PostulacionRequisito::where('postulacion_id', $p->id)->update([
                    'estado'                 => EstadoRequisito::VALIDADO->value,
                    'verificado_por_user_id' => $admin->id,
                    'verificado_at'          => now(),
                ]);

                // (CU9) Confirmar inscripcion al turno -> asigna grupo + INSCRITO.
                $insc = InscripcionService::confirmar($p, $turno->id);

                $cod = $insc->grupo->codigo ?? '?';
                $grupos[$cod] = ($grupos[$cod] ?? 0) + 1;
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $this->command->warn("  Postulacion {$p->id} error: " . $e->getMessage());
            }
        }

        $this->command->info('========================================');
        $this->command->info("Validados e inscritos: {$ok}");
        $this->command->info("Fallidos: {$fail}");
        foreach ($grupos as $g => $n) {
            $this->command->info("  Grupo {$g}: +{$n} inscritos");
        }
    }
}
