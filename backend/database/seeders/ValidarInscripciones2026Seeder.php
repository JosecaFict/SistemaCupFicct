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
| Valida (requisitos VALIDADO) y confirma la inscripcion de lotes de
| postulantes pendientes (PAGO_APROBADO) de la gestion 1-2026 a un turno.
|
| Replica lo que haria el encargado a mano (CU8 + CU9):
|   - marca todos los requisitos como VALIDADO
|   - InscripcionService::confirmar(turno) -> asigna grupo con cupo, genera
|     codigo y pasa la postulacion a INSCRITO.
|
| El grupo lo elige el sistema: primer grupo ACTIVO del turno con cupo libre
| (ordenado por codigo). Cada lote toma los SIGUIENTES pendientes por orden
| (los ya inscritos salen de la cola, no se repiten).
|
| Lotes a procesar: ver BATCHES.
*/
class ValidarInscripciones2026Seeder extends Seeder
{
    private const GESTION_ID = 3;

    /** Lotes: turno (codigo) => cantidad a validar/inscribir. */
    private const BATCHES = [
        ['turno' => 'M', 'cantidad' => 64], // Manana -> M2 (1->65)
        ['turno' => 'T', 'cantidad' => 64], // Tarde  -> T2 (1->65)
        ['turno' => 'N', 'cantidad' => 64], // Noche  -> N2 (1->65)
    ];

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

        foreach (self::BATCHES as $batch) {
            $this->procesarLote($admin, $batch['turno'], $batch['cantidad']);
        }
    }

    private function procesarLote(User $admin, string $turnoCodigo, int $cantidad): void
    {
        $this->command->info('----------------------------------------');
        $turno = Turno::where('codigo', $turnoCodigo)->first();
        if (!$turno) {
            $this->command->error("Turno no encontrado: {$turnoCodigo}");
            return;
        }
        $this->command->info("Lote: {$cantidad} -> turno [{$turno->id}] {$turno->codigo} ({$turno->nombre})");

        $pendientes = Postulacion::where('gestion_cup_id', self::GESTION_ID)
            ->where('estado', EstadoPostulacion::PAGO_APROBADO->value)
            ->orderBy('id')
            ->limit($cantidad)
            ->get();

        $this->command->info('Pendientes tomados: ' . $pendientes->count());

        $ok = 0;
        $fail = 0;
        $grupos = [];

        foreach ($pendientes as $p) {
            try {
                PostulacionRequisito::where('postulacion_id', $p->id)->update([
                    'estado'                 => EstadoRequisito::VALIDADO->value,
                    'verificado_por_user_id' => $admin->id,
                    'verificado_at'          => now(),
                ]);

                $insc = InscripcionService::confirmar($p, $turno->id);

                $cod = $insc->grupo->codigo ?? '?';
                $grupos[$cod] = ($grupos[$cod] ?? 0) + 1;
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $this->command->warn("  Postulacion {$p->id} error: " . $e->getMessage());
            }
        }

        $this->command->info("  Inscritos: {$ok} | Fallidos: {$fail}");
        foreach ($grupos as $g => $n) {
            $this->command->info("  Grupo {$g}: +{$n}");
        }
    }
}
