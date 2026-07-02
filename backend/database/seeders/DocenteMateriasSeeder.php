<?php

namespace Database\Seeders;

use App\Models\AsignacionDocente;
use App\Models\Materia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
| DocenteMateriasSeeder (Ciclo 3)
| --------------------------------------------------------------------------
| Habilita a los docentes existentes en las materias que ya vienen dando,
| ademas de asignar combinaciones realistas a los docentes demo:
|
|   - prof.matematica -> MAT + FIS  (perfil ingeniero, similar al ejemplo Juanito)
|   - prof.fisica     -> FIS + MAT
|   - prof.ingles     -> ING
|   - prof.computacion -> COMP + MAT
|
| Ademas: para cualquier otro docente detectado en 'asignaciones_docente',
| habilita automaticamente las materias que ya viene dictando (para no
| romper el flujo historico).
|
| Idempotente: usa syncWithoutDetaching, se puede correr multiples veces.
*/
class DocenteMateriasSeeder extends Seeder
{
    public function run(): void
    {
        $rolDocenteId = Rol::where('codigo', 'DOCENTE')->value('id');
        if (!$rolDocenteId) {
            $this->command?->warn('No existe rol DOCENTE. Se omite DocenteMateriasSeeder.');
            return;
        }

        $materiasPorCodigo = Materia::pluck('id', 'codigo')->all();
        // Si por alguna razon no estan las materias base, salir.
        if (empty($materiasPorCodigo)) {
            $this->command?->warn('No hay materias en catalogo. Se omite DocenteMateriasSeeder.');
            return;
        }

        // 1. Combinaciones fijas para los docentes demo.
        $combinacionesDemo = [
            'prof.matematica@cup-ficct.local'  => ['MAT', 'FIS'],
            'prof.fisica@cup-ficct.local'      => ['FIS', 'MAT'],
            'prof.ingles@cup-ficct.local'      => ['ING'],
            'prof.computacion@cup-ficct.local' => ['COMP', 'MAT'],
        ];

        foreach ($combinacionesDemo as $email => $codigosMateria) {
            $docente = User::where('email', $email)->where('role_id', $rolDocenteId)->first();
            if (!$docente) {
                continue;
            }
            $materiaIds = array_values(array_filter(array_map(
                fn ($cod) => $materiasPorCodigo[$cod] ?? null,
                $codigosMateria
            )));
            $docente->materiasHabilitadas()->syncWithoutDetaching($materiaIds);
        }

        // 2. Para otros docentes (no demo) que ya tienen asignaciones historicas:
        //    se autohabilita en las materias que ya vienen dando.
        $emailsDemo = array_keys($combinacionesDemo);
        $otrosDocentes = User::where('role_id', $rolDocenteId)
            ->whereNotIn('email', $emailsDemo)
            ->get();

        foreach ($otrosDocentes as $docente) {
            $materiaIdsHistoricas = AsignacionDocente::where('docente_user_id', $docente->id)
                ->join('gestion_materias', 'asignaciones_docente.gestion_materia_id', '=', 'gestion_materias.id')
                ->pluck('gestion_materias.materia_id')
                ->unique()
                ->values()
                ->all();

            if (!empty($materiaIdsHistoricas)) {
                $docente->materiasHabilitadas()->syncWithoutDetaching($materiaIdsHistoricas);
            }
        }

        $total = DB::table('docente_materias')->count();
        $this->command?->info("DocenteMateriasSeeder OK. Habilitaciones activas: {$total}");
    }
}
