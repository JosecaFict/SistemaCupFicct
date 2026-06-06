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
use App\Models\Turno;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
| cup:rebalancear-historicas
| --------------------------------------------------------------------------
| Comando de mantenimiento (one-off, IDEMPOTENTE) para las gestiones de demo
| 1-2025 y 2-2025. Deja la demo prolija SIN tocar el esquema ni otras gestiones:
|
|   1) REPARTO DE TURNOS: ajusta cuantos inscritos hay por turno al objetivo
|      (por defecto 134 Manana / 134 Tarde / 132 Noche = 400), moviendo el
|      minimo de postulaciones entre turnos.
|   2) LLENADO SECUENCIAL: dentro de cada turno llena los grupos en orden
|      (M1 hasta su capacidad y recien M2, etc.), reproduciendo la regla real
|      de InscripcionService. Recalcula inscritos_actuales.
|   3) BORRADO DE GRUPOS VACIOS: elimina los grupos que quedan en 0 (ej. M3,
|      M4, M5...) porque no se justifican. Solo borra si NO tienen inscritos
|      ni inscripciones (las asignaciones de docente a esos grupos se borran
|      en cascada, segun la FK).
|   4) REQUISITOS: genera el checklist (que el seeder no creo) y lo marca
|      VALIDADO, para que los documentos figuren aprobados.
|
| Seguridad:
|   - Solo afecta las gestiones cuyos codigos se pasen (por defecto las de demo).
|   - No altera tablas; reasigna turno_id/grupo_id, recalcula contadores,
|     borra solo grupos vacios y agrega/actualiza requisitos.
|   - Idempotente: re-ejecutarlo deja el mismo resultado.
|   - Si no encuentra las gestiones, no hace nada (seguro en migrate:fresh).
|   - --dry-run ejecuta todo en una transaccion y la revierte (no persiste).
*/
class RebalancearGestionesHistoricas extends Command
{
    protected $signature = 'cup:rebalancear-historicas
                            {--codigos=1-2025,2-2025 : Codigos de gestion a procesar, separados por coma}
                            {--turnos=M:134,T:134,N:132 : Objetivo de inscritos por turno (codigo:cantidad)}
                            {--dry-run : Simula los cambios sin guardarlos}';

    protected $description = 'Reparte turnos, llena grupos en orden, borra grupos vacios y aprueba requisitos (demo)';

    public function handle(): int
    {
        $codigos = collect(explode(',', (string) $this->option('codigos')))
            ->map(fn ($c) => trim($c))->filter()->values()->all();

        $target = $this->parseTurnos((string) $this->option('turnos'));
        if (empty($target)) {
            $this->error('Objetivo de turnos invalido. Formato esperado: M:134,T:134,N:132');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $gestiones = GestionCup::whereIn('codigo', $codigos)->orderBy('codigo')->get();
        if ($gestiones->isEmpty()) {
            $this->warn('No se encontro ninguna gestion con esos codigos: ' . implode(', ', $codigos) . '. Nada que hacer.');
            return self::SUCCESS;
        }

        // Usuario verificador para los requisitos: encargado o coordinador;
        // si no hay, cualquier usuario.
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
                $this->reasignarTurnos($gestion, $target);
                $this->rebalancearGrupos($gestion);
                $this->eliminarGruposVacios($gestion);
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
     * Ajusta cuantos inscritos hay por turno al objetivo, moviendo el minimo
     * de postulaciones desde los turnos con sobrante hacia los que tienen
     * deficit. Actualiza postulacion.turno_id e inscripcion.turno_id.
     */
    private function reasignarTurnos(GestionCup $gestion, array $target): void
    {
        $turnos = Turno::whereIn('codigo', array_keys($target))->get()->keyBy('codigo');
        foreach (array_keys($target) as $cod) {
            if (!isset($turnos[$cod])) {
                $this->warn("  Turno {$cod} no existe en el catalogo; se omite el reparto de turnos.");
                return;
            }
        }

        $inscritos = Postulacion::where('gestion_cup_id', $gestion->id)
            ->where('estado', EstadoPostulacion::INSCRITO)
            ->orderBy('codigo_postulante')->orderBy('id')
            ->get();

        if ($inscritos->count() !== array_sum($target)) {
            $this->warn("  Inscritos={$inscritos->count()} != objetivo=" . array_sum($target)
                . '; se omite el reparto de turnos (se respeta el actual).');
            return;
        }

        $idPorCod  = $turnos->map(fn ($t) => $t->id);                       // cod => turno_id
        $codPorId  = $turnos->mapWithKeys(fn ($t) => [$t->id => $t->codigo]); // turno_id => cod

        // Agrupar inscritos por codigo de turno actual.
        $porTurno = array_fill_keys(array_keys($target), []);
        $pool = []; // postulaciones a reubicar (turno fuera de objetivo o sobrante)
        foreach ($inscritos as $p) {
            $cod = $codPorId[$p->turno_id] ?? null;
            if ($cod !== null && isset($porTurno[$cod])) {
                $porTurno[$cod][] = $p;
            } else {
                $pool[] = $p;
            }
        }

        // Sacar el sobrante de cada turno al pool.
        foreach ($target as $cod => $n) {
            if (count($porTurno[$cod]) > $n) {
                $sobrantes = array_splice($porTurno[$cod], $n); // deja n, saca el resto
                $pool = array_merge($pool, $sobrantes);
            }
        }

        // Cubrir el deficit de cada turno con el pool.
        $movimientos = 0;
        foreach ($target as $cod => $n) {
            while (count($porTurno[$cod]) < $n && !empty($pool)) {
                $p = array_shift($pool);
                $p->turno_id = $idPorCod[$cod];
                $p->save();
                Inscripcion::where('postulacion_id', $p->id)
                    ->update(['turno_id' => $idPorCod[$cod]]);
                $porTurno[$cod][] = $p;
                $movimientos++;
            }
        }

        $resumen = implode(' / ', array_map(
            fn ($c) => "{$c}=" . count($porTurno[$c]),
            array_keys($target)
        ));
        $this->line("  Reparto turnos: {$resumen} ({$movimientos} movidos)");
    }

    /**
     * Reasigna los inscritos de cada turno a sus grupos en orden de codigo,
     * llenando cada grupo hasta su capacidad antes de pasar al siguiente.
     * Recalcula inscritos_actuales y actualiza tambien la inscripcion.
     */
    private function rebalancearGrupos(GestionCup $gestion): void
    {
        $gruposPorTurno = Grupo::where('gestion_cup_id', $gestion->id)
            ->get()->groupBy('turno_id');

        foreach ($gruposPorTurno as $turnoId => $grupos) {
            $destinos = $grupos
                ->where('estado', 'ACTIVO')
                ->sortBy(fn ($g) => $this->ordenCodigo($g->codigo))
                ->values();

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

            $postulaciones = Postulacion::where('gestion_cup_id', $gestion->id)
                ->where('turno_id', $turnoId)
                ->where('estado', EstadoPostulacion::INSCRITO)
                ->orderBy('codigo_postulante')->orderBy('id')
                ->get();

            $idx = 0;
            foreach ($postulaciones as $post) {
                while ($idx < $destinos->count()
                    && $destinos[$idx]->inscritos_actuales >= $destinos[$idx]->capacidad) {
                    $idx++;
                }

                if ($idx >= $destinos->count()) {
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

            foreach ($grupos as $g) {
                $g->save();
            }
            foreach ($destinos as $g) {
                $this->line("  {$g->codigo}: {$g->inscritos_actuales}/{$g->capacidad}");
            }
        }
    }

    /**
     * Borra los grupos que quedaron VACIOS (0 inscritos) para que no se vean
     * grupos sin gente. Solo borra si nada los referencia; las asignaciones de
     * docente a esos grupos se eliminan en cascada (FK cascadeOnDelete).
     */
    private function eliminarGruposVacios(GestionCup $gestion): void
    {
        $vacios = Grupo::where('gestion_cup_id', $gestion->id)
            ->where('inscritos_actuales', 0)
            ->orderBy('codigo')
            ->get();

        $borrados = [];
        foreach ($vacios as $g) {
            $referenciado = Inscripcion::where('grupo_id', $g->id)->exists()
                || Postulacion::where('grupo_id', $g->id)->exists();

            if ($referenciado) {
                $this->warn("  Grupo {$g->codigo} marca 0 pero tiene referencias; NO se borra.");
                continue;
            }

            $codigo = $g->codigo;
            $g->delete();
            $borrados[] = $codigo;
        }

        $this->line($borrados
            ? '  Grupos vacios borrados: ' . implode(', ', $borrados)
            : '  No habia grupos vacios para borrar.');
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

    /** Parsea "M:134,T:134,N:132" -> ['M'=>134,'T'=>134,'N'=>132]. */
    private function parseTurnos(string $raw): array
    {
        $target = [];
        foreach (explode(',', $raw) as $par) {
            $partes = explode(':', trim($par));
            if (count($partes) === 2 && $partes[0] !== '' && is_numeric($partes[1])) {
                $target[trim($partes[0])] = (int) $partes[1];
            }
        }
        return $target;
    }

    /** Extrae el numero final del codigo de grupo (M1 -> 1, T10 -> 10). */
    private function ordenCodigo(string $codigo): int
    {
        return preg_match('/(\d+)$/', $codigo, $m) ? (int) $m[1] : 0;
    }
}
