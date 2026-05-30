<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GestionCup;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/*
| DashboardController -- Resumen para el dashboard del administrador/encargado.
*/
class DashboardController extends Controller
{
    public function resumen(): JsonResponse
    {
        $gestion = GestionCup::where('estado', 'ACTIVA')->latest()->first();

        $base = $gestion
            ? Postulacion::where('gestion_cup_id', $gestion->id)
            : Postulacion::query();

        return response()->json([
            'gestion_activa'    => $gestion,
            'usuarios_total'    => User::count(),
            'usuarios_activos'  => User::where('activo', true)->count(),
            'postulaciones'     => [
                'total'           => (clone $base)->count(),
                'preinscritos'    => (clone $base)->where('estado', 'PREINSCRITO')->count(),
                'form_generado'   => (clone $base)->where('estado', 'FORMULARIO_GENERADO')->count(),
                'pago_aprobado'   => (clone $base)->where('estado', 'PAGO_APROBADO')->count(),
                'observados'      => (clone $base)->where('estado', 'OBSERVADO')->count(),
                'inscritos'       => (clone $base)->where('estado', 'INSCRITO')->count(),
                'anulados'        => (clone $base)->where('estado', 'ANULADO')->count(),
            ],
            'pagos' => [
                'aprobados' => Pago::where('estado', 'APROBADO')->count(),
                'pendientes'=> Pago::where('estado', 'PENDIENTE')->count(),
                'rechazados'=> Pago::where('estado', 'RECHAZADO')->count(),
            ],
        ]);
    }
}
