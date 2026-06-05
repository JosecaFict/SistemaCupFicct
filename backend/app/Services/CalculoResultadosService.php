<?php

namespace App\Services;

use App\Enums\EstadoNota;
use App\Enums\EstadoPostulacion;
use App\Enums\EstadoResultado;
use App\Enums\OpcionAceptada;
use App\Models\CupoCarrera;
use App\Models\GestionCup;
use App\Models\GestionMateria;
use App\Models\Postulacion;
use App\Models\Resultado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
| CalculoResultadosService (Ciclo 2 - CU16, CU17)
| --------------------------------------------------------------------------
| Algoritmo completo de calculo de resultados de una gestion CUP:
|
|   PASO 1 - Filtrar postulantes INSCRITOS de la gestion.
|   PASO 2 - Calcular nota final ponderada por postulante:
|              nota_materia = SUM(nota_examen * ponderacion_examen/100)
|              nota_final   = SUM(nota_materia * ponderacion_materia/100)
|   PASO 3 - Marcar REPROBADOS:
|              * descalificados (al menos 1 nota < nota_minima)
|              * promedio final < nota_minima
|   PASO 4 - Ranking global de elegibles (nota_final DESC, fecha_preins ASC).
|   PASO 5 - Asignar primeras opciones por carrera (ordenadas por ranking).
|   PASO 6 - Asignar segundas opciones a los no aceptados.
|   PASO 7 - Quien no quedo asignado: SIN_CUPO.
|
| Desempate: cuando 2 postulantes tienen la misma nota, gana el que se
| preinscribio primero (fecha_preinscripcion ASC). Es objetivo y sin sesgo.
*/
class CalculoResultadosService
{
    /**
     * Verifica si la gestion esta lista para ser calculada.
     * Devuelve estructura con flags y razones (para mostrar al UI).
     */
    public static function estadoGestion(GestionCup $gestion): array
    {
        // Postulaciones que esperan calculo (INSCRITO o ya calculado antes)
        $postulaciones = Postulacion::where('gestion_cup_id', $gestion->id)
            ->whereIn('estado', [
                EstadoPostulacion::INSCRITO->value,
                EstadoPostulacion::ACEPTADO->value,
                EstadoPostulacion::REPROBADO->value,
                EstadoPostulacion::SIN_CUPO->value,
            ])
            ->count();

        $gestionMateriaIds = GestionMateria::where('gestion_cup_id', $gestion->id)->pluck('id');

        // Notas pendientes (que esperan validacion)
        $notasPendientes = DB::table('notas as n')
            ->join('postulaciones as p', 'n.postulacion_id', '=', 'p.id')
            ->where('p.gestion_cup_id', $gestion->id)
            ->where('n.estado', EstadoNota::PENDIENTE->value)
            ->count();

        // Notas rechazadas (tambien deben corregirse)
        $notasRechazadas = DB::table('notas as n')
            ->join('postulaciones as p', 'n.postulacion_id', '=', 'p.id')
            ->where('p.gestion_cup_id', $gestion->id)
            ->where('n.estado', EstadoNota::RECHAZADA->value)
            ->count();

        // Notas faltantes: cada postulante debe tener (cantidad_examenes * #materias)
        $cantMaterias = $gestionMateriaIds->count();
        $notasEsperadas = $postulaciones * $gestion->cantidad_examenes * $cantMaterias;
        $notasCargadas = DB::table('notas as n')
            ->join('postulaciones as p', 'n.postulacion_id', '=', 'p.id')
            ->where('p.gestion_cup_id', $gestion->id)
            ->count();

        $resultadosCalculados = Resultado::whereIn('postulacion_id',
            Postulacion::where('gestion_cup_id', $gestion->id)->pluck('id'))->count();

        $resultadosPublicados = Resultado::whereIn('postulacion_id',
            Postulacion::where('gestion_cup_id', $gestion->id)->pluck('id'))
            ->where('publicado', true)->count();

        $bloqueado = $notasPendientes > 0 || $notasRechazadas > 0 || $notasCargadas < $notasEsperadas;

        return [
            'gestion_id'             => $gestion->id,
            'gestion_codigo'         => $gestion->codigo,
            'postulaciones'          => $postulaciones,
            'cantidad_materias'      => $cantMaterias,
            'cantidad_examenes'      => (int) $gestion->cantidad_examenes,
            'notas_esperadas'        => $notasEsperadas,
            'notas_cargadas'         => $notasCargadas,
            'notas_pendientes'       => $notasPendientes,
            'notas_rechazadas'       => $notasRechazadas,
            'puede_calcularse'       => !$bloqueado && $postulaciones > 0,
            'razon_bloqueo'          => $bloqueado ? self::razonBloqueo($notasPendientes, $notasRechazadas, $notasCargadas, $notasEsperadas) : null,
            'resultados_calculados'  => $resultadosCalculados,
            'resultados_publicados'  => $resultadosPublicados,
            'tiene_calculo_previo'   => $resultadosCalculados > 0,
            'tiene_publicacion'      => $resultadosPublicados > 0,
        ];
    }

    private static function razonBloqueo(int $pend, int $rech, int $cargadas, int $esperadas): string
    {
        $partes = [];
        if ($cargadas < $esperadas) $partes[] = "faltan " . ($esperadas - $cargadas) . " notas por cargar";
        if ($pend > 0)              $partes[] = "{$pend} notas en estado PENDIENTE de validacion";
        if ($rech > 0)              $partes[] = "{$rech} notas RECHAZADAS deben corregirse";
        return implode('; ', $partes);
    }

    /**
     * Ejecuta el calculo completo. Devuelve un resumen.
     * Si ya existe calculo previo, lo sobrescribe (recalculo).
     */
    public static function calcular(GestionCup $gestion, int $calculadoPorUserId): array
    {
        return DB::transaction(function () use ($gestion, $calculadoPorUserId) {
            // Pre-cargar materias con su ponderacion
            $gestionMaterias = GestionMateria::where('gestion_cup_id', $gestion->id)->get();
            $cupos = CupoCarrera::where('gestion_cup_id', $gestion->id)->get()
                ->mapWithKeys(fn ($c) => [$c->carrera_id => (int) $c->cupos])->toArray();

            $notaMinima = (float) $gestion->nota_minima_aprobacion;
            $ponderacionesExamen = self::ponderacionesPorExamen($gestion);

            // Cargar postulaciones con sus notas
            $postulaciones = Postulacion::where('gestion_cup_id', $gestion->id)
                ->whereIn('estado', [
                    EstadoPostulacion::INSCRITO->value,
                    EstadoPostulacion::ACEPTADO->value,
                    EstadoPostulacion::REPROBADO->value,
                    EstadoPostulacion::SIN_CUPO->value,
                ])
                ->get(['id', 'carrera_primera_id', 'carrera_segunda_id', 'fecha_preinscripcion']);

            $idsPostulacion = $postulaciones->pluck('id');
            $todasNotas = DB::table('notas')
                ->whereIn('postulacion_id', $idsPostulacion)
                ->get(['postulacion_id', 'gestion_materia_id', 'numero_examen', 'valor', 'descalifica'])
                ->groupBy('postulacion_id');

            // Calculo por postulante
            $calculos = [];
            foreach ($postulaciones as $p) {
                $notas = $todasNotas->get($p->id, collect());
                $descalificado = $notas->contains(fn ($n) => (bool) $n->descalifica);

                $notaFinal = 0.0;
                foreach ($gestionMaterias as $gm) {
                    $notasMat = $notas->where('gestion_materia_id', $gm->id);
                    $notaMatRaw = 0.0;
                    foreach ($notasMat as $n) {
                        $peso = $ponderacionesExamen[(int) $n->numero_examen] ?? 0;
                        $notaMatRaw += (float) $n->valor * ($peso / 100);
                    }
                    $notaFinal += $notaMatRaw * ($gm->ponderacion / 100);
                }

                $calculos[$p->id] = [
                    'postulacion'        => $p,
                    'nota_final'         => round($notaFinal, 2),
                    'descalificado'      => $descalificado,
                    'aprobado_promedio'  => $notaFinal >= $notaMinima,
                ];
            }

            // Elegibles (no descalificados Y promedio aprobado)
            $elegibles = array_filter($calculos, fn ($c) => !$c['descalificado'] && $c['aprobado_promedio']);

            // Ordenar por nota DESC, en empate por fecha_preinscripcion ASC
            uasort($elegibles, function ($a, $b) {
                $cmp = $b['nota_final'] <=> $a['nota_final'];
                if ($cmp !== 0) return $cmp;
                return strcmp(
                    (string) $a['postulacion']->fecha_preinscripcion,
                    (string) $b['postulacion']->fecha_preinscripcion
                );
            });

            // Asignar 1ras opciones
            $cuposRestantes = $cupos;
            $aceptados = [];
            $sinAsignar = [];
            foreach ($elegibles as $id => $calc) {
                $c1 = $calc['postulacion']->carrera_primera_id;
                if (($cuposRestantes[$c1] ?? 0) > 0) {
                    $aceptados[$id] = ['carrera_id' => $c1, 'opcion' => OpcionAceptada::PRIMERA];
                    $cuposRestantes[$c1]--;
                } else {
                    $sinAsignar[$id] = $calc;
                }
            }
            // Asignar 2das opciones
            foreach ($sinAsignar as $id => $calc) {
                $c2 = $calc['postulacion']->carrera_segunda_id;
                if ($c2 && ($cuposRestantes[$c2] ?? 0) > 0) {
                    $aceptados[$id] = ['carrera_id' => $c2, 'opcion' => OpcionAceptada::SEGUNDA];
                    $cuposRestantes[$c2]--;
                }
            }

            // Ranking global
            $ranking = [];
            $r = 1;
            foreach ($elegibles as $id => $_) {
                $ranking[$id] = $r++;
            }

            // Limpiar resultados previos para esta gestion (recalculo total)
            Resultado::whereIn('postulacion_id', $idsPostulacion)->delete();

            // Generar filas de resultados
            $now = Carbon::now();
            $rows = [];
            $contadores = ['ACEPTADO' => 0, 'REPROBADO' => 0, 'SIN_CUPO' => 0];

            foreach ($calculos as $id => $calc) {
                $estadoFinal = EstadoResultado::REPROBADO;
                $opcion = OpcionAceptada::NINGUNA;
                $carreraAsignadaId = null;
                $motivo = '';

                if ($calc['descalificado']) {
                    $estadoFinal = EstadoResultado::REPROBADO;
                    $motivo = 'Descalificado: nota menor a la minima en al menos un examen.';
                } elseif (!$calc['aprobado_promedio']) {
                    $estadoFinal = EstadoResultado::REPROBADO;
                    $motivo = 'Promedio final menor a la nota minima de aprobacion.';
                } elseif (isset($aceptados[$id])) {
                    $estadoFinal = EstadoResultado::ACEPTADO;
                    $opcion = $aceptados[$id]['opcion'];
                    $carreraAsignadaId = $aceptados[$id]['carrera_id'];
                    $motivo = $opcion === OpcionAceptada::PRIMERA
                        ? 'Aceptado en su primera opcion de carrera.'
                        : 'Aceptado en su segunda opcion de carrera (1ra estaba llena).';
                } else {
                    $estadoFinal = EstadoResultado::SIN_CUPO;
                    $motivo = 'Aprobado pero sin cupo: sus carreras elegidas estaban llenas.';
                }

                $nuevoEstadoPost = match ($estadoFinal) {
                    EstadoResultado::ACEPTADO  => EstadoPostulacion::ACEPTADO,
                    EstadoResultado::REPROBADO => EstadoPostulacion::REPROBADO,
                    EstadoResultado::SIN_CUPO  => EstadoPostulacion::SIN_CUPO,
                    default                    => EstadoPostulacion::REPROBADO,
                };
                Postulacion::where('id', $id)->update(['estado' => $nuevoEstadoPost->value]);
                $contadores[$estadoFinal->value]++;

                $rows[] = [
                    'postulacion_id'        => $id,
                    'nota_final'            => $calc['nota_final'],
                    'ranking_global'        => $ranking[$id] ?? null,
                    'carrera_asignada_id'   => $carreraAsignadaId,
                    'opcion_aceptada'       => $opcion->value,
                    'estado_final'          => $estadoFinal->value,
                    'motivo'                => $motivo,
                    'fecha_calculo'         => $now,
                    'calculado_por_user_id' => $calculadoPorUserId,
                    'publicado'             => false,
                    'fecha_publicacion'     => null,
                    'publicado_por_user_id' => null,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('resultados')->insert($chunk);
            }

            BitacoraService::registrar(
                $resultadosPrevios = false ? 'RECALCULO_EJECUTADO' : 'CALCULO_RESULTADOS_FINALIZADO',
                'gestion_cup',
                $gestion->id,
                [
                    'aceptados'  => $contadores['ACEPTADO'],
                    'reprobados' => $contadores['REPROBADO'],
                    'sin_cupo'   => $contadores['SIN_CUPO'],
                ]
            );

            return [
                'total'              => count($calculos),
                'aceptados'          => $contadores['ACEPTADO'],
                'reprobados'         => $contadores['REPROBADO'],
                'sin_cupo'           => $contadores['SIN_CUPO'],
                'cupos_disponibles'  => $cupos,
                'cupos_restantes'    => $cuposRestantes,
            ];
        });
    }

    /**
     * Publica todos los resultados de la gestion (los hace visibles al
     * publico via codigo de postulante). Solo aplica a ACEPTADOS.
     */
    public static function publicar(GestionCup $gestion, int $publicadoPorUserId): int
    {
        $idsPostulacion = Postulacion::where('gestion_cup_id', $gestion->id)->pluck('id');

        $cant = Resultado::whereIn('postulacion_id', $idsPostulacion)
            ->where('publicado', false)
            ->update([
                'publicado'             => true,
                'fecha_publicacion'     => Carbon::now(),
                'publicado_por_user_id' => $publicadoPorUserId,
            ]);

        BitacoraService::registrar('RESULTADOS_PUBLICADOS', 'gestion_cup', $gestion->id, ['cantidad' => $cant]);

        return $cant;
    }

    /**
     * Despublica los resultados (vuelve publicado=false). Util si hay error
     * y se necesita corregir antes de hacer publica la lista de nuevo.
     */
    public static function despublicar(GestionCup $gestion): int
    {
        $idsPostulacion = Postulacion::where('gestion_cup_id', $gestion->id)->pluck('id');
        return Resultado::whereIn('postulacion_id', $idsPostulacion)
            ->where('publicado', true)
            ->update([
                'publicado'             => false,
                'fecha_publicacion'     => null,
                'publicado_por_user_id' => null,
            ]);
    }

    /** Convierte fechas_examenes a un map [numero => ponderacion]. */
    private static function ponderacionesPorExamen(GestionCup $gestion): array
    {
        return $gestion->fechasExamenes()
            ->orderBy('numero')
            ->get()
            ->mapWithKeys(fn ($f) => [(int) $f->numero => (int) $f->ponderacion])
            ->toArray();
    }
}
