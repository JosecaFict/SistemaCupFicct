<?php

namespace Database\Seeders;

use App\Enums\EstadoPago;
use App\Enums\EstadoPostulacion;
use App\Models\Carrera;
use App\Models\Colegio;
use App\Models\GestionCup;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\Postulacion;
use App\Services\RequisitoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
| PostulantesDemo2026Seeder
| --------------------------------------------------------------------------
| Carga masiva de postulantes para la gestion 1-2026, dejandolos en estado
| PAGO_APROBADO (preinscripcion + formulario + pago aprobado), listos para
| que el encargado valide requisitos y confirme la inscripcion uno por uno.
|
| Replica el flujo real (PreinscripcionController + PagoService):
|   Persona -> Postulacion(PREINSCRITO) -> checklist requisitos -> PAGO_APROBADO
|   + Pago APROBADO.
|
| Reglas pedidas:
|   - tipo_documento = CI_BO (todos)
|   - expedido       = aleatorio entre departamentos (determinista por doc,
|                      para que reejecutar no duplique personas)
|   - carreras 1ra/2da = aleatorias (distintas) de las carreras activas
|
| Idempotente: si la persona (por documento) ya tiene postulacion en la
| gestion, se omite (no duplica los que ya cargaste a mano).
|
| Datos en: database/seeders/data/postulantes_2026.txt (TSV de 11 columnas):
|   documento  nombre  ap_paterno  ap_materno  fecha_nac(d/m/Y)  telefono
|   email  direccion  colegio  ciudad  anio_egreso
*/
class PostulantesDemo2026Seeder extends Seeder
{
    private const DEPTOS = ['SC', 'LP', 'CB', 'OR', 'PT', 'TJ', 'CH', 'BN', 'PD'];

    public function run(): void
    {
        // 1. Resolver la gestion 1-2026 (no exige ACTIVA: es carga directa).
        $gestion = GestionCup::where('codigo', '1-2026')->first()
            ?? GestionCup::where('codigo', 'like', '%2026%')
                ->orWhere('nombre', 'like', '%2026%')
                ->first();

        if (!$gestion) {
            $this->command->error('No se encontro la gestion 1-2026. Gestiones existentes:');
            foreach (GestionCup::get(['id', 'codigo', 'nombre', 'estado']) as $g) {
                $this->command->line("  [{$g->id}] {$g->codigo} - {$g->nombre} ({$g->estado})");
            }
            return;
        }

        $this->command->info("Gestion destino: [{$gestion->id}] {$gestion->codigo} - {$gestion->nombre} ({$gestion->estado})");

        // 2. Carreras activas para el random.
        $carreraIds = Carrera::where('activa', true)->pluck('id')->all();
        if (count($carreraIds) < 1) {
            $this->command->error('No hay carreras activas para asignar.');
            return;
        }
        $this->command->info('Carreras activas disponibles: ' . count($carreraIds));

        // 3. Monto y modo (mismo criterio que CU7).
        $monto = (float) ($gestion->costo_inscripcion ?? 700);
        $modo  = config('stripe.mode', 'simulated');
        $this->command->info("Monto del pago: Bs {$monto} | modo: {$modo}");

        // 4. Leer y parsear el archivo.
        $path = database_path('seeders/data/postulantes_2026.txt');
        if (!is_file($path)) {
            $this->command->error("No existe el archivo de datos: {$path}");
            return;
        }
        $lineas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $creados = 0;
        $omitidos = 0;
        $errores = 0;

        foreach ($lineas as $n => $linea) {
            $col = preg_split('/\t+/', trim($linea));
            if (count($col) !== 11) {
                $errores++;
                $this->command->warn('  Fila ' . ($n + 1) . " ignorada (campos != 11).");
                continue;
            }

            [$doc, $nombre, $apPat, $apMat, $fnac, $tel, $email, $dir, $colegioNom, $ciudad, $anio] = $col;

            try {
                DB::transaction(function () use (
                    $doc, $nombre, $apPat, $apMat, $fnac, $tel, $email, $dir,
                    $colegioNom, $ciudad, $anio, $gestion, $carreraIds, $monto, $modo,
                    &$creados, &$omitidos
                ) {
                    // Dedup por documento: si ya tiene postulacion en la gestion, omitir.
                    $persona = Persona::where('tipo_documento', 'CI_BO')
                        ->where('documento', $doc)
                        ->first();

                    if ($persona) {
                        $yaPostula = Postulacion::where('persona_id', $persona->id)
                            ->where('gestion_cup_id', $gestion->id)
                            ->exists();
                        if ($yaPostula) {
                            $omitidos++;
                            return;
                        }
                    } else {
                        $fechaNac = \DateTime::createFromFormat('d/m/Y', $fnac);
                        $persona = Persona::create([
                            'tipo_documento'   => 'CI_BO',
                            'documento'        => $doc,
                            'expedido'         => self::expedidoDeterminista($doc),
                            'nombre'           => $nombre,
                            'apellido_paterno' => $apPat,
                            'apellido_materno' => $apMat,
                            'sexo'             => null,
                            'fecha_nacimiento' => $fechaNac ? $fechaNac->format('Y-m-d') : null,
                            'email'            => $email,
                            'telefono'         => $tel,
                            'direccion'        => $dir,
                        ]);
                    }

                    // Colegio (firstOrCreate por nombre, igual que el controller).
                    $colegio = null;
                    if ($colegioNom !== '') {
                        $colegio = Colegio::firstOrCreate(
                            ['nombre' => $colegioNom],
                            ['ciudad' => $ciudad ?: null]
                        );
                    }

                    // Carreras aleatorias (distintas).
                    [$c1, $c2] = self::carrerasRandom($carreraIds);

                    $postulacion = Postulacion::create([
                        'persona_id'           => $persona->id,
                        'gestion_cup_id'       => $gestion->id,
                        'colegio_id'           => $colegio?->id,
                        'anio_egreso_colegio'  => is_numeric($anio) ? (int) $anio : null,
                        'carrera_primera_id'   => $c1,
                        'carrera_segunda_id'   => $c2,
                        'estado'               => EstadoPostulacion::PAGO_APROBADO,
                        'fecha_preinscripcion' => now(),
                    ]);

                    // Checklist de requisitos (PENDIENTE) para que el encargado valide.
                    RequisitoService::asegurarChecklist($postulacion);

                    // Pago APROBADO (carga directa, sin pasar por la pasarela).
                    Pago::create([
                        'postulacion_id'  => $postulacion->id,
                        'monto'           => $monto,
                        'moneda'          => 'BOB',
                        'modo'            => $modo,
                        'estado'          => EstadoPago::APROBADO,
                        'referencia'      => 'REF-SEED-' . $doc,
                        'payload'         => ['seed' => true, 'origen' => 'PostulantesDemo2026Seeder'],
                        'fecha_aprobacion' => now(),
                    ]);

                    $creados++;
                });
            } catch (\Throwable $e) {
                $errores++;
                $this->command->warn('  Fila ' . ($n + 1) . " ({$doc}) error: " . $e->getMessage());
            }
        }

        $this->command->info('========================================');
        $this->command->info("Creados:  {$creados}");
        $this->command->info("Omitidos: {$omitidos} (ya tenian postulacion en la gestion)");
        $this->command->info("Errores:  {$errores}");
        $this->command->info('Total procesado: ' . count($lineas));
    }

    /** Expedido determinista por documento (mismo doc -> mismo depto en reejecuciones). */
    private static function expedidoDeterminista(string $doc): string
    {
        return self::DEPTOS[abs(crc32($doc)) % count(self::DEPTOS)];
    }

    /** Dos carreras distintas al azar (o una sola si solo hay una activa). */
    private static function carrerasRandom(array $ids): array
    {
        if (count($ids) === 1) {
            return [$ids[0], null];
        }
        $k = array_rand($ids, 2);
        return [$ids[$k[0]], $ids[$k[1]]];
    }
}
