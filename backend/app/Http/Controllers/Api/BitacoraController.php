<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AsignacionDocente;
use App\Models\Bitacora;
use App\Models\GestionCup;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| BitacoraController -- Lectura de la bitacora basica.
| Solo lectura; los eventos se insertan desde BitacoraService.
| Resuelve el nombre legible de cada entidad (entidad + entidad_id) para la UI.
*/
class BitacoraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Bitacora::with('user:id,nombre,apellidos,email');

        if ($evento = $request->query('evento')) {
            $q->where('evento', $evento);
        }
        if ($entidad = $request->query('entidad')) {
            $q->where('entidad', $entidad);
        }

        $page = $q->orderByDesc('id')->paginate(30);
        $this->resolverNombres($page->getCollection());

        return response()->json($page);
    }

    /** Agrega 'entidad_nombre' (nombre legible del registro) a cada entrada. */
    private function resolverNombres(Collection $entradas): void
    {
        // Agrupar ids por tipo de entidad
        $porTipo = [];
        foreach ($entradas as $b) {
            if ($b->entidad && $b->entidad_id) {
                $porTipo[$b->entidad][$b->entidad_id] = true;
            }
        }

        $mapas = [];
        foreach ($porTipo as $tipo => $ids) {
            $mapas[$tipo] = $this->nombresDe($tipo, array_keys($ids));
        }

        foreach ($entradas as $b) {
            $b->entidad_nombre = ($b->entidad && $b->entidad_id)
                ? ($mapas[$b->entidad][$b->entidad_id] ?? null)
                : null;
        }
    }

    /** [id => nombre] de un tipo de entidad. */
    private function nombresDe(string $tipo, array $ids): array
    {
        return match ($tipo) {
            'user' => User::whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($u) => [$u->id => trim($u->nombre . ' ' . $u->apellidos)])->all(),

            'postulacion' => Postulacion::with('persona:id,nombre,apellido_paterno,apellido_materno')
                ->whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($p) => [$p->id => $this->nombrePostulante($p)])->all(),

            'inscripcion' => Inscripcion::with('postulacion.persona:id,nombre,apellido_paterno,apellido_materno')
                ->whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($i) => [$i->id => $this->nombrePostulante($i->postulacion)])->all(),

            'pago' => Pago::with('postulacion.persona:id,nombre,apellido_paterno,apellido_materno')
                ->whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($pg) => [$pg->id => $this->nombrePostulante($pg->postulacion)])->all(),

            'gestion_cup' => GestionCup::whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($g) => [$g->id => $g->codigo])->all(),

            'asignacion_docente' => AsignacionDocente::with(['docente:id,nombre,apellidos', 'grupo:id,codigo'])
                ->whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($a) => [$a->id => trim(
                    ($a->docente ? trim($a->docente->nombre . ' ' . $a->docente->apellidos) : 'Docente')
                    . ($a->grupo ? ' (' . $a->grupo->codigo . ')' : '')
                )])->all(),

            default => [],
        };
    }

    private function nombrePostulante($postulacion): ?string
    {
        if (!$postulacion) {
            return null;
        }
        $persona = $postulacion->persona;
        $nombre = $persona
            ? trim($persona->nombre . ' ' . ($persona->apellido_paterno ?? '') . ' ' . ($persona->apellido_materno ?? ''))
            : null;
        return $nombre ?: ($postulacion->codigo_postulante ?: null);
    }
}
