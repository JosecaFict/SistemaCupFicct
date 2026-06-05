<?php

namespace Database\Seeders;

use App\Enums\EstadoNota;
use App\Enums\EstadoPago;
use App\Enums\EstadoPostulacion;
use App\Enums\EstadoResultado;
use App\Enums\OpcionAceptada;
use App\Models\Ambiente;
use App\Models\Carrera;
use App\Models\Colegio;
use App\Models\CupoCarrera;
use App\Models\FechaExamen;
use App\Models\GestionCup;
use App\Models\GestionMateria;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\Postulacion;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| GestionesHistoricasSeeder (Ciclo 2)
| --------------------------------------------------------------------------
| Genera 2 gestiones CERRADAS y PUBLICADAS con 400 postulantes cada una,
| para tener datos historicos realistas en dashboards y reportes.
|
| Gestiones generadas:
|   1-2025 (primera convocatoria 2025)
|   2-2025 (segunda convocatoria 2025)
|
| Por cada gestion crea:
|   - Configuracion: 3 examenes (30%+30%+40%), nota minima 60, capacidad 70
|   - 4 materias evaluadas con ponderacion 25% c/u (suma 100)
|   - Cupos por carrera: 75 c/u (300 totales)
|   - Grupos: 15 (5 por turno: M T N), capacidad 70
|   - 400 postulantes (personas + postulaciones + pagos APROBADOS + inscripciones)
|   - Asignaciones docente (15 grupos x 4 materias = 60 asignaciones)
|     siguiendo el patron grupos impares/pares y horarios L/M/V vs M/J/S.
|   - 4800 notas validadas (400 postulantes x 4 materias x 3 examenes)
|   - 400 resultados publicados con el algoritmo de cupos:
|       1. Filtrar descalificados (nota <60 en cualquier examen-materia)
|       2. Calcular nota final ponderada (suma materias x ponderacion)
|       3. Filtrar quienes tienen nota final < nota_minima (60)
|       4. Ranking por nota final DESC
|       5. Llenar primeras opciones por carrera
|       6. Llenar segundas opciones con cupos restantes
|       7. SIN_CUPO al resto
|   - Estado final: CERRADA. Resultados PUBLICADOS.
*/
class GestionesHistoricasSeeder extends Seeder
{
    private const POSTULANTES_POR_GESTION = 400;
    private const CAPACIDAD_GRUPO = 70;
    private const NOTA_MINIMA = 60;
    private const CUPOS_POR_CARRERA = 75;

    private ?int $encargadoId = null;

    private const NOMBRES = [
        'Juan', 'Maria', 'Carlos', 'Ana', 'Luis', 'Sofia', 'Pedro', 'Lucia',
        'Jose', 'Carmen', 'Miguel', 'Rosa', 'Diego', 'Patricia', 'Mario',
        'Andrea', 'Roberto', 'Veronica', 'Marco', 'Daniela', 'Pablo',
        'Camila', 'Sergio', 'Valeria', 'Eduardo', 'Gabriela', 'Fernando',
        'Paola', 'Ricardo', 'Adriana', 'Alejandro', 'Natalia', 'Ivan',
        'Cristina', 'Ramiro', 'Silvia', 'Walter', 'Karen', 'Oscar', 'Jessica',
    ];

    private const APELLIDOS = [
        'Mamani', 'Quispe', 'Choque', 'Condori', 'Flores', 'Lopez', 'Vargas',
        'Rojas', 'Salazar', 'Mendoza', 'Torres', 'Rivera', 'Cardenas',
        'Vasquez', 'Gutierrez', 'Ramos', 'Roca', 'Aguilar', 'Castro',
        'Romero', 'Perez', 'Sanchez', 'Rodriguez', 'Martinez', 'Gonzales',
        'Hernandez', 'Soliz', 'Bautista', 'Ortiz', 'Salinas', 'Cuellar',
        'Banegas', 'Pinto', 'Rivero', 'Padilla', 'Soruco', 'Velasco',
        'Suarez', 'Lima', 'Ribera',
    ];

    private const COLEGIOS = [
        ['nombre' => 'Colegio Nacional Florida',         'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio Don Bosco',                'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio La Salle',                 'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio Hugo Banzer Suarez',       'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio Marista',                  'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio Adventista',               'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio Aleman Mariscal Braun',    'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio Santa Ana',                'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio San Ignacio',              'ciudad' => 'Santa Cruz'],
        ['nombre' => 'Colegio Bolivar',                  'ciudad' => 'Santa Cruz'],
    ];

    private const EXPEDIDOS = ['SC', 'CB', 'LP', 'OR', 'PT', 'TJ', 'BN', 'PA'];
    private const SEXOS = ['M', 'F'];

    public function run(): void
    {
        // Cache del id del encargado para no consultar 800 veces
        $this->encargadoId = User::whereHas('rol', fn($q) => $q->where('codigo', 'ENCARGADO'))->first()?->id;
        if (!$this->encargadoId) {
            throw new \RuntimeException('No hay usuario ENCARGADO. Corre UsuariosDemoSeeder primero.');
        }

        $this->command->info('Generando gestion 1-2025 (400 postulantes)...');
        $this->generarGestion(
            codigo: '1-2025',
            nombre: 'Primera Convocatoria CUP 2025',
            fechaPreIni: '2025-01-10',
            fechaPreFin: '2025-01-31',
            fechasExamenes: ['2025-02-15', '2025-03-01', '2025-03-15'],
            ofsetCI: 5000000,
            ofsetCodigo: 0,        // 0000001 - 0000400
        );

        $this->command->info('Generando gestion 2-2025 (400 postulantes)...');
        $this->generarGestion(
            codigo: '2-2025',
            nombre: 'Segunda Convocatoria CUP 2025',
            fechaPreIni: '2025-07-10',
            fechaPreFin: '2025-07-31',
            fechasExamenes: ['2025-08-15', '2025-09-01', '2025-09-15'],
            ofsetCI: 6000000,
            ofsetCodigo: 400,      // 0000401 - 0000800
        );

        $this->command->info('Listo. 2 gestiones historicas con 800 postulantes en total.');
    }

    private function generarGestion(
        string $codigo,
        string $nombre,
        string $fechaPreIni,
        string $fechaPreFin,
        array  $fechasExamenes,
        int    $ofsetCI,
        int    $ofsetCodigo = 0,
    ): void {
        DB::transaction(function () use ($codigo, $nombre, $fechaPreIni, $fechaPreFin, $fechasExamenes, $ofsetCI, $ofsetCodigo) {

            // ===== 1. Gestion CUP =====
            $gestion = GestionCup::create([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'fecha_inicio_preinscripcion' => $fechaPreIni,
                'fecha_cierre_preinscripcion' => $fechaPreFin,
                'cantidad_examenes' => 3,
                'capacidad_maxima_grupo' => self::CAPACIDAD_GRUPO,
                'estimado_postulantes' => self::POSTULANTES_POR_GESTION,
                'turnos_habilitados' => 'M,T,N',
                'estado' => 'CERRADA',
                'nota_minima_aprobacion' => self::NOTA_MINIMA,
            ]);

            // ===== 2. Fechas examenes (3 con ponderacion 30/30/40) =====
            $ponderaciones = [30, 30, 40];
            foreach ($fechasExamenes as $idx => $fecha) {
                FechaExamen::create([
                    'gestion_cup_id' => $gestion->id,
                    'numero' => $idx + 1,
                    'fecha' => $fecha,
                    'ponderacion' => $ponderaciones[$idx],
                ]);
            }

            // ===== 3. Gestion materias (4 con ponderacion 25 c/u) =====
            $materias = Materia::orderBy('id')->get();
            $gestionMaterias = [];
            foreach ($materias as $m) {
                $gm = GestionMateria::create([
                    'gestion_cup_id' => $gestion->id,
                    'materia_id' => $m->id,
                    'ponderacion' => 25,
                ]);
                $gestionMaterias[$m->codigo] = $gm;
            }

            // ===== 4. Cupos por carrera (75 c/u) =====
            $carreras = Carrera::orderBy('id')->get();
            foreach ($carreras as $c) {
                CupoCarrera::create([
                    'gestion_cup_id' => $gestion->id,
                    'carrera_id' => $c->id,
                    'cupos' => self::CUPOS_POR_CARRERA,
                ]);
            }

            // ===== 5. Grupos (5 por turno: M1-M5, T1-T5, N1-N5) =====
            $turnos = Turno::whereIn('codigo', ['M', 'T', 'N'])->orderBy('id')->get()->keyBy('codigo');
            $ambientes = Ambiente::orderBy('id')->get();
            $grupos = [];
            $ambIdx = 0;
            foreach (['M', 'T', 'N'] as $codTurno) {
                for ($i = 1; $i <= 5; $i++) {
                    $codigoGrupo = $codTurno . $i;
                    $grupo = Grupo::create([
                        'gestion_cup_id' => $gestion->id,
                        'turno_id' => $turnos[$codTurno]->id,
                        'ambiente_id' => $ambientes[$ambIdx % $ambientes->count()]->id,
                        'codigo' => $codigoGrupo,
                        'capacidad' => self::CAPACIDAD_GRUPO,
                        'inscritos_actuales' => 0,
                        'estado' => 'ACTIVO',
                    ]);
                    $grupos[$codigoGrupo] = $grupo;
                    $ambIdx++;
                }
            }

            // ===== 6. Asignaciones docente (15 grupos x 4 materias = 60) =====
            $this->generarAsignacionesDocente($gestion, $grupos, $gestionMaterias, $ambientes);

            // ===== 7. Personas + Postulaciones + Pagos + Inscripciones =====
            [$postulaciones, $personas] = $this->generarPostulantes(
                $gestion, $carreras, $grupos, $turnos, $ofsetCI, $ofsetCodigo
            );

            // ===== 8. Notas (4800 por gestion: 400 x 4 x 3) =====
            $this->generarNotas($gestion, $postulaciones, $gestionMaterias);

            // ===== 9. Calculo de resultados =====
            $this->calcularResultados($gestion, $postulaciones, $gestionMaterias);

            $this->command->info("  Gestion {$codigo} OK.");
        });
    }

    // -------------------------------------------------------------------
    // 6. ASIGNACIONES DOCENTE
    // -------------------------------------------------------------------
    private function generarAsignacionesDocente($gestion, array $grupos, array $gestionMaterias, $ambientes): void
    {
        $docentes = User::whereHas('rol', fn($q) => $q->where('codigo', 'DOCENTE'))
            ->orderBy('email')
            ->get()
            ->keyBy(function ($u) {
                if (str_contains($u->email, 'matematica')) return 'MAT';
                if (str_contains($u->email, 'fisica'))     return 'FIS';
                if (str_contains($u->email, 'ingles'))     return 'ING';
                if (str_contains($u->email, 'computacion'))return 'COMP';
                return $u->email;
            });

        $now = now();
        $rows = [];

        foreach ($grupos as $codigoGrupo => $grupo) {
            // Determinar si es grupo IMPAR (M1, M3, M5, T1, ...) o PAR (M2, M4, T2, ...)
            $numGrupo = (int) substr($codigoGrupo, 1);
            $esImpar = $numGrupo % 2 === 1;

            // Patron de horarios segun par/impar:
            // IMPAR: L/Mi/V -> MAT, FIS  |  Ma/J/S -> ING, COMP
            // PAR:   L/Mi/V -> ING, COMP |  Ma/J/S -> MAT, FIS
            $patron = $esImpar
                ? ['LU,MI,VI' => ['MAT', 'FIS'], 'MA,JU,SA' => ['ING', 'COMP']]
                : ['LU,MI,VI' => ['ING', 'COMP'], 'MA,JU,SA' => ['MAT', 'FIS']];

            // Determinar horas segun turno del grupo
            $codTurno = substr($codigoGrupo, 0, 1);
            $hora1Ini = match($codTurno) { 'M'=>'08:00','T'=>'13:00','N'=>'18:00' };
            $hora1Fin = match($codTurno) { 'M'=>'10:00','T'=>'15:00','N'=>'20:00' };
            $hora2Ini = match($codTurno) { 'M'=>'10:00','T'=>'15:00','N'=>'20:00' };
            $hora2Fin = match($codTurno) { 'M'=>'12:00','T'=>'17:00','N'=>'22:00' };

            $ambIdx = 0;
            foreach ($patron as $dias => $materiasOrden) {
                foreach ($materiasOrden as $orden => $codMat) {
                    $horaIni = $orden === 0 ? $hora1Ini : $hora2Ini;
                    $horaFin = $orden === 0 ? $hora1Fin : $hora2Fin;

                    $rows[] = [
                        'gestion_cup_id' => $gestion->id,
                        'grupo_id' => $grupo->id,
                        'gestion_materia_id' => $gestionMaterias[$codMat]->id,
                        'docente_user_id' => $docentes[$codMat]->id,
                        'ambiente_id' => $grupo->ambiente_id,
                        'dias_semana' => $dias,
                        'hora_inicio' => $horaIni,
                        'hora_fin' => $horaFin,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::table('asignaciones_docente')->insert($rows);
    }

    // -------------------------------------------------------------------
    // 7. POSTULANTES (personas + postulaciones + pagos + inscripciones)
    // -------------------------------------------------------------------
    private function generarPostulantes($gestion, $carreras, array $grupos, $turnos, int $ofsetCI, int $ofsetCodigo = 0): array
    {
        // Pre-crear colegios para esta gestion (si no existen)
        $colegios = [];
        foreach (self::COLEGIOS as $c) {
            $colegios[] = Colegio::firstOrCreate(
                ['nombre' => $c['nombre']],
                ['ciudad' => $c['ciudad']]
            );
        }

        // Distribucion de carreras (1ra opcion): SIS 35%, INF 25%, RED 20%, ROB 20%
        $carrerasPorCodigo = $carreras->keyBy('codigo');
        $distribCarreras = array_merge(
            array_fill(0, 35, 'SIS'),
            array_fill(0, 25, 'INF'),
            array_fill(0, 20, 'RED'),
            array_fill(0, 20, 'ROB'),
        );

        // Grupos por turno
        $gruposPorTurno = [
            'M' => array_filter(array_keys($grupos), fn($k) => str_starts_with($k, 'M')),
            'T' => array_filter(array_keys($grupos), fn($k) => str_starts_with($k, 'T')),
            'N' => array_filter(array_keys($grupos), fn($k) => str_starts_with($k, 'N')),
        ];

        // Contador para distribuir postulantes equitativamente por turno
        // 400 / 3 turnos ~= 133 por turno
        $postulaciones = [];
        $personas = [];

        for ($i = 1; $i <= self::POSTULANTES_POR_GESTION; $i++) {
            $nombre = self::NOMBRES[array_rand(self::NOMBRES)];
            $apellidoP = self::APELLIDOS[array_rand(self::APELLIDOS)];
            $apellidoM = self::APELLIDOS[array_rand(self::APELLIDOS)];
            $sexo = self::SEXOS[array_rand(self::SEXOS)];
            $expedido = self::EXPEDIDOS[array_rand(self::EXPEDIDOS)];
            $documento = (string)($ofsetCI + $i);
            $fechaNac = Carbon::create(2006, rand(1, 12), rand(1, 28));

            // Persona
            $persona = Persona::create([
                'tipo_documento' => 'CI_BO',
                'documento' => $documento,
                'expedido' => $expedido,
                'nombre' => $nombre,
                'apellido_paterno' => $apellidoP,
                'apellido_materno' => $apellidoM,
                'sexo' => $sexo,
                'fecha_nacimiento' => $fechaNac,
                'email' => strtolower("{$nombre}.{$apellidoP}{$i}@mail.com"),
                'telefono' => '7' . rand(1000000, 9999999),
                'direccion' => 'Av. Demo ' . rand(100, 9999),
            ]);

            // Asignar 1ra y 2da carrera
            $codCarrera1 = $distribCarreras[($i - 1) % count($distribCarreras)];
            $codCarrera2 = null;
            if (rand(1, 100) <= 70) { // 70% elige 2da opcion
                do {
                    $codCarrera2 = ['SIS','INF','RED','ROB'][array_rand(['SIS','INF','RED','ROB'])];
                } while ($codCarrera2 === $codCarrera1);
            }

            // Asignar turno y grupo (round robin entre turnos y grupos)
            $codTurno = ['M', 'T', 'N'][($i - 1) % 3];
            $codigosGruposTurno = array_values($gruposPorTurno[$codTurno]);
            $codGrupo = $codigosGruposTurno[($i - 1) % count($codigosGruposTurno)];

            // Codigo postulante: 7 digitos, con offset para no duplicar entre gestiones
            $codigoPostulante = str_pad((string)($ofsetCodigo + $i), 7, '0', STR_PAD_LEFT);

            $colegio = $colegios[array_rand($colegios)];

            // Postulacion (estado INSCRITO, pagos aprobados, inscripcion confirmada)
            $postulacion = Postulacion::create([
                'persona_id' => $persona->id,
                'gestion_cup_id' => $gestion->id,
                'colegio_id' => $colegio->id,
                'anio_egreso_colegio' => 2024,
                'carrera_primera_id' => $carrerasPorCodigo[$codCarrera1]->id,
                'carrera_segunda_id' => $codCarrera2 ? $carrerasPorCodigo[$codCarrera2]->id : null,
                'turno_id' => $turnos[$codTurno]->id,
                'grupo_id' => $grupos[$codGrupo]->id,
                'codigo_postulante' => $codigoPostulante,
                'estado' => EstadoPostulacion::INSCRITO,
                'fecha_preinscripcion' => $gestion->fecha_inicio_preinscripcion,
                'fecha_inscripcion' => $gestion->fecha_cierre_preinscripcion,
            ]);

            // Pago APROBADO
            Pago::create([
                'postulacion_id' => $postulacion->id,
                'monto' => 100.00,
                'moneda' => 'BOB',
                'modo' => 'simulated',
                'estado' => EstadoPago::APROBADO,
                'referencia' => 'REF-DEMO-' . strtoupper(Str::random(8)),
                'stripe_payment_intent_id' => 'pi_demo_' . Str::random(12),
                'stripe_client_secret' => 'cs_demo_' . Str::random(16),
                'payload' => ['demo' => true],
                'fecha_aprobacion' => $gestion->fecha_cierre_preinscripcion,
            ]);

            // Inscripcion confirmada
            Inscripcion::create([
                'postulacion_id' => $postulacion->id,
                'grupo_id' => $grupos[$codGrupo]->id,
                'turno_id' => $turnos[$codTurno]->id,
                'confirmada_por_user_id' => $this->encargadoId,
                'codigo_postulante' => $codigoPostulante,
                'fecha_inscripcion' => $gestion->fecha_cierre_preinscripcion,
            ]);

            // Incrementar contador del grupo
            $grupos[$codGrupo]->increment('inscritos_actuales');

            $postulaciones[$postulacion->id] = $postulacion;
            $personas[$persona->id] = $persona;
        }

        return [$postulaciones, $personas];
    }

    // -------------------------------------------------------------------
    // 8. NOTAS (4800 = 400 x 4 x 3)
    // -------------------------------------------------------------------
    private function generarNotas($gestion, array $postulaciones, array $gestionMaterias): void
    {
        $docentes = User::whereHas('rol', fn($q) => $q->where('codigo', 'DOCENTE'))
            ->orderBy('email')->get()->keyBy(function ($u) {
                if (str_contains($u->email, 'matematica')) return 'MAT';
                if (str_contains($u->email, 'fisica'))     return 'FIS';
                if (str_contains($u->email, 'ingles'))     return 'ING';
                if (str_contains($u->email, 'computacion'))return 'COMP';
                return $u->email;
            });
        $coordinador = User::whereHas('rol', fn($q) => $q->where('codigo', 'COORDINADOR'))->first();
        $fechaValidacion = Carbon::parse($gestion->fecha_cierre_preinscripcion)->addMonths(2);
        $now = now();

        // Calibracion de distribucion:
        //   ~70% saca >=60 en TODAS sus materias (no descalifica)
        //   ~15% descalifica (>=1 nota <60 en algun examen)
        //   ~15% mixto (algunas notas medias)
        $rows = [];
        $i = 0;
        foreach ($postulaciones as $postulacion) {
            $i++;
            $perfilSemilla = $i % 100;
            // perfil <= 70: alto, 70-85: medio, >85: descalifica
            $perfilAlto = $perfilSemilla < 70;
            $perfilDescalifica = $perfilSemilla >= 85;

            foreach ($gestionMaterias as $codMat => $gm) {
                $docenteId = $docentes[$codMat]->id;

                for ($examen = 1; $examen <= 3; $examen++) {
                    if ($perfilAlto) {
                        $valor = rand(65, 98);
                    } elseif ($perfilDescalifica) {
                        // Aleatoriamente 1-2 notas seran <60
                        $valor = (rand(1, 100) <= 30) ? rand(20, 59) : rand(60, 90);
                    } else {
                        $valor = rand(50, 85);
                    }

                    $descalifica = $valor < self::NOTA_MINIMA;

                    $rows[] = [
                        'postulacion_id' => $postulacion->id,
                        'gestion_materia_id' => $gm->id,
                        'numero_examen' => $examen,
                        'valor' => $valor,
                        'docente_user_id' => $docenteId,
                        'estado' => EstadoNota::VALIDADA->value,
                        'validado_por_user_id' => $coordinador?->id,
                        'fecha_validacion' => $fechaValidacion,
                        'observacion' => null,
                        'descalifica' => $descalifica,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Insert en chunks de 500 para evitar limite de PG
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('notas')->insert($chunk);
        }
    }

    // -------------------------------------------------------------------
    // 9. CALCULO DE RESULTADOS
    // -------------------------------------------------------------------
    private function calcularResultados($gestion, array $postulaciones, array $gestionMaterias): void
    {
        $admin = User::whereHas('rol', fn($q) => $q->where('codigo', 'ADMINISTRADOR'))->first();
        $coordinador = User::whereHas('rol', fn($q) => $q->where('codigo', 'COORDINADOR'))->first();

        $ponderacionesExamen = [1 => 30, 2 => 30, 3 => 40];
        $now = now();
        $fechaCalculo = Carbon::parse($gestion->fecha_cierre_preinscripcion)->addMonths(4);
        $fechaPublicacion = $fechaCalculo->copy()->addDays(7);

        // 1. Calcular nota_final y descalificacion por postulante
        $calculos = [];
        foreach ($postulaciones as $postulacion) {
            $notas = DB::table('notas')
                ->where('postulacion_id', $postulacion->id)
                ->get();

            $descalificado = $notas->contains(fn($n) => $n->descalifica);

            // Nota por materia (ponderada por examen)
            $notaPorMateria = [];
            foreach ($gestionMaterias as $codMat => $gm) {
                $notasMat = $notas->where('gestion_materia_id', $gm->id);
                $suma = 0;
                foreach ($notasMat as $n) {
                    $suma += $n->valor * ($ponderacionesExamen[$n->numero_examen] / 100);
                }
                $notaPorMateria[$codMat] = $suma;
            }
            // Nota final = suma de (nota_materia * ponderacion_materia / 100)
            $notaFinal = 0;
            foreach ($gestionMaterias as $codMat => $gm) {
                $notaFinal += $notaPorMateria[$codMat] * ($gm->ponderacion / 100);
            }

            $calculos[$postulacion->id] = [
                'postulacion' => $postulacion,
                'nota_final' => round($notaFinal, 2),
                'descalificado' => $descalificado,
                'aprobado_promedio' => $notaFinal >= self::NOTA_MINIMA,
            ];
        }

        // 2. Filtrar elegibles (no descalificados y con promedio >= nota_minima)
        $elegibles = array_filter(
            $calculos,
            fn($c) => !$c['descalificado'] && $c['aprobado_promedio']
        );

        // 3. Ordenar por nota_final DESC (ranking)
        uasort($elegibles, fn($a, $b) => $b['nota_final'] <=> $a['nota_final']);

        // 4. Asignar 1ras opciones
        $cuposRestantes = [];
        foreach (CupoCarrera::where('gestion_cup_id', $gestion->id)->get() as $cc) {
            $cuposRestantes[$cc->carrera_id] = $cc->cupos;
        }

        $aceptados = []; // postulacion_id => ['carrera_id' => X, 'opcion' => 'PRIMERA'|'SEGUNDA']
        $sinAsignar = [];

        foreach ($elegibles as $postId => $calc) {
            $c1 = $calc['postulacion']->carrera_primera_id;
            if (($cuposRestantes[$c1] ?? 0) > 0) {
                $aceptados[$postId] = ['carrera_id' => $c1, 'opcion' => OpcionAceptada::PRIMERA];
                $cuposRestantes[$c1]--;
            } else {
                $sinAsignar[$postId] = $calc;
            }
        }

        // 5. Asignar 2das opciones a los no aceptados
        foreach ($sinAsignar as $postId => $calc) {
            $c2 = $calc['postulacion']->carrera_segunda_id;
            if ($c2 && ($cuposRestantes[$c2] ?? 0) > 0) {
                $aceptados[$postId] = ['carrera_id' => $c2, 'opcion' => OpcionAceptada::SEGUNDA];
                $cuposRestantes[$c2]--;
            }
        }

        // 6. Asignar ranking global (entre elegibles, por nota DESC)
        $rankingGlobal = [];
        $r = 1;
        foreach ($elegibles as $postId => $_) {
            $rankingGlobal[$postId] = $r++;
        }

        // 7. Crear filas de resultados (bulk insert)
        $rows = [];
        foreach ($calculos as $postId => $calc) {
            $estadoFinal = EstadoResultado::SIN_CUPO;
            $opcion = OpcionAceptada::NINGUNA;
            $carreraAsignadaId = null;
            $motivo = '';

            if ($calc['descalificado']) {
                $motivo = 'Descalificado: nota menor a la minima en al menos un examen.';
            } elseif (!$calc['aprobado_promedio']) {
                $motivo = 'Promedio final menor a la nota minima de aprobacion.';
            } elseif (isset($aceptados[$postId])) {
                $estadoFinal = EstadoResultado::ACEPTADO;
                $opcion = $aceptados[$postId]['opcion'];
                $carreraAsignadaId = $aceptados[$postId]['carrera_id'];
                $motivo = $opcion === OpcionAceptada::PRIMERA
                    ? 'Aceptado en su primera opcion de carrera.'
                    : 'Aceptado en su segunda opcion de carrera (1ra estaba llena).';
            } else {
                $motivo = 'Sin cupo: ambas carreras estaban llenas en su rango de ranking.';
            }

            // Actualizar estado de postulacion segun resultado
            $nuevoEstadoPost = $estadoFinal === EstadoResultado::ACEPTADO
                ? EstadoPostulacion::ACEPTADO
                : EstadoPostulacion::SIN_CUPO;
            $calc['postulacion']->update(['estado' => $nuevoEstadoPost]);

            $rows[] = [
                'postulacion_id' => $postId,
                'nota_final' => $calc['nota_final'],
                'ranking_global' => $rankingGlobal[$postId] ?? null,
                'carrera_asignada_id' => $carreraAsignadaId,
                'opcion_aceptada' => $opcion->value,
                'estado_final' => $estadoFinal->value,
                'motivo' => $motivo,
                'fecha_calculo' => $fechaCalculo,
                'calculado_por_user_id' => $coordinador?->id,
                'publicado' => true,
                'fecha_publicacion' => $fechaPublicacion,
                'publicado_por_user_id' => $admin?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('resultados')->insert($chunk);
        }
    }
}
