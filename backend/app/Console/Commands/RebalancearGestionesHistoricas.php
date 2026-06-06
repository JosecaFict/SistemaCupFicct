<?php

namespace App\Console\Commands;

use App\Enums\EstadoPostulacion;
use App\Enums\EstadoRequisito;
use App\Models\GestionCup;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Postulacion;
use App\Models\PostulacionRequisito;
use App\Models\Requisito;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
| cup:rebalancear-historicas
| --------------------------------------------------------------------------
| Comando de mantenimiento (one-off, IDEMPOTENTE) para las gestiones de demo
| 1-2025 y 2-2025. Hace dos cosas, SIN tocar el esquema ni otras gestiones:
|
|   1) Re-balancea los grupos para que se llenen EN ORDEN (M1 hasta su
|      capacidad y recien M2; igual T1->T2, N1->N2), respetando el turno que
|      ya tiene cada inscrito. Reproduce la regla real de InscripcionService
|      (orderBy('codigo') + cupo libre), que el seeder no aplico (uso
|      round-robin y por eso ningun grupo quedaba lleno).
|   2) Genera el checklist de requisitos (que el seeder NO creo) y lo marca
|      como VALIDADO, para que los documentos figuren aprobados.
|
| Seguridad:
|   - Solo afecta las gestiones cuyos codigos se pasen (por defecto las dos
|     de demo). Jamas toca una gestion ACTIVA distinta.
|   - No borra ni altera tablas; solo reasigna grupo_id, recalcula contadores
|     y agrega/actualiza filas de requisitos.
|   - Idempotente: correrlo de nuevo deja el mismo resultado.
|   - Si no encuentra las gestiones, no hace nada (seguro en migrate:fresh).
|   - --dry-run ejecuta todo dentro de una transaccion y la revierte, para
|     ver el resultado sin persistir.
*/
class RebalancearGestionesHistoricas extends Command
{
    protected $signature = 'cup:rebalancear-historicas
                            {--codigos=1-2025,2-2025 : Codigos de gestion a procesar, separados por coma}
                            {--dry-run : Simula los cambios sin guardarlos}';

    protected $description = 'Re-balancea grupos (llenado secuencial) y aprueba requisitos de las gestiones de demo';

    public function handle(): int
    {
        $codigos = collect(explode(',', (string) $this->option('codigos')))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->values()
            ->all();

        $dryRun = (bool) $this->option('dry-run');

        $gestiones = GestionCup::whereIn('codigo', $codigos)->orderBy('codigo')->get();
        if ($gestiones->isEmpty()) {
            $this->warn('No se encontro ninguna gestion con esos codigos: ' . implode(', ', $codigos) . '. Nada que hacer.');
            return self::SUCCESS;
        }

        // Usuario verificador para los requisitos: encargado o coordinador;
        // si no hay, cualquier usuario (el campo es nullable, pero preferimos uno real).
        $verificadorId = User::whereHas('rol', fn ($q) => $q->whereIn('codigo', ['ENCARGADO', 'COORDINADOR']))
            ->orderBy('id')->value('id')
            ?? User::orderBy('id')->value('id');

        if ($dryRun) {
            $this->comment('** MODO DRY-RUN: no se guardara ningun cambio. **');
        }

        foreach ($gestiones as $gestion) {
            $this->info("=== Gestion {$gestion->codigo} (id {$gestion->id}) ===");

            DB::beginTransaction();
            try {
                $this->rebalancearGrupos($gestion);
                $this->aprobarRequisitos($gestion, $verificadorId);

                if ($dryRun) {
                    DB::rollBack();
                    $this->comment('  (dry-run: cambios revertidos)');
                } else {
                    DB::commit();
                    $this->info('  Guardado.');
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("  Error en {$gestion->codigo}: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        $this->info('Listo.');
        return self::SUCCESS;
    }

    /**
     * Reasigna los inscritos de cada turno a sus grupos en orden de codigo,
     * llenando cada grupo hasta su capacidad antes de pasar al siguiente.
     * Recalcula inscritos_actuales y actualiza tambien la fila de inscripcion.
     */
    private function rebalancearGrupos(GestionCup $gestion): void
    {
        $gruposPorTurno = Grupo::where('gestion_cup_id', $gestion->id)
            ->get()
            ->groupBy('turno_id');

        foreach ($gruposPorTurno as $turnoId => $grupos) {
            // Solo grupos ACTIVOS reciben inscritos (igual que InscripcionService),
            // ordenados por el numero del codigo: M1, M2, ..., M10.
            $destinos = $grupos
                ->where('estado', 'ACTIVO')
                ->sortBy(fn ($g) => $this->ordenCodigo($g->codigo))
                ->values();

            // Reset de contadores de TODOS los grupos del turno.
            foreach ($grupos as $g) {
                $g->inscritos_actuales = 0;
            }

            if ($destinos->isEmpty()) {
                $this->warn("  Turno {$turnoId}: sin grupos ACTIVOS, se omite.");
                foreach ($grupos as $g) {
                    $g->save();
                }
                continue;
            }

            // Inscritos de este turno, en orden estable y reproducible.
            $postulaciones = Postulacion::where('gestion_cup_id', $gestion->id)
                ->where('turno_id', $turnoId)
                ->where('estado', EstadoPostulacion::INSCRITO)
                ->orderBy('codigo_postulante')
                ->orderBy('id')
                ->get();

            $idx = 0;
            foreach ($postulaciones as $post) {
                // Avanzar hasta el primer grupo con cupo libre.
                while ($idx < $destinos->count()
                    && $destinos[$idx]->inscritos_actuales >= $destinos[$idx]->capacidad) {
                    $idx++;
                }

                if ($idx >= $destinos->count()) {
                    // No deberia pasar (la capacidad ya alcanzaba con round-robin),
                    // pero si pasara, dejamos al ultimo grupo sin romper.
                    $idx = $destinos->count() - 1;
                    $this->warn("  Turno {$turnoId}: capacidad insuficiente, sobrecargando {$destinos[$idx]->codigo}.");
                }

                $grupo = $destinos[$idx];
                $grupo->inscritos_actuales++;

                if ((int) $post->grupo_id !== (int) $grupo->id) {
                    $post->grupo_id = $grupo->id;
                    $post->save();
                    Inscripcion::where('postulacion_id', $post->id)
                        ->update(['grupo_id' => $grupo->id]);
                }
            }

            // Persistir contadores recalculados de todos los grupos del turno.
            foreach ($grupos as $g) {
                $g->save();
            }
            foreach ($destinos as $g) {
                $this->line("  {$g->codigo}: {$g->inscritos_actuales}/{$g->capacidad}");
            }
        }
    }

    /**
     * Crea (si falta) el checklist de requisitos de cada inscrito y lo marca
     * VALIDADO. Idempotente via firstOrNew.
     */
    private function aprobarRequisitos(GestionCup $gestion, ?int $verificadorId): void
    {
        $requisitos = Requisito::orderBy('orden')->get();
        if ($requisitos->isEmpty()) {
            $this->warn('  No hay requisitos en el catalogo; se omite la aprobacion.');
            return;
        }

        $postulaciones = Postulacion::where('gestion_cup_id', $gestion->id)
            ->where('estado', EstadoPostulacion::INSCRITO)
            ->get();

        $creados = 0;
        $total = 0;
        foreach ($postulaciones as $post) {
            foreach ($requisitos as $r) {
                $pr = PostulacionRequisito::firstOrNew([
                    'postulacion_id' => $post->id,
                    'requisito_id'   => $r->id,
                ]);
                if (!$pr->exists) {
                    $creados++;
                }
                $pr->estado = EstadoRequisito::VALIDADO;
                $pr->observacion = null;
                $pr->verificado_por_user_id = $verificadorId;
                $pr->verificado_at = now();
                $pr->save();
                $total++;
            }
        }

        $this->line("  Requisitos: {$total} marcados VALIDADO ({$creados} nuevos) en {$postulaciones->count()} inscritos.");
    }

    /** Extrae el numero final del codigo de grupo (M1 -> 1, T10 -> 10). */
    private function ordenCodigo(string $codigo): int
    {
        return preg_match('/(\d+)$/', $codigo, $m) ? (int) $m[1] : 0;
    }
}
