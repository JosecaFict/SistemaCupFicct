<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BitacoraController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GestionCupController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\InscripcionController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\PingController;
use App\Http\Controllers\Api\PostulanteController;
use App\Http\Controllers\Api\PreinscripcionController;
use App\Http\Controllers\Api\RequisitoVerificacionController;
use App\Http\Controllers\Api\ResultadoAdminController;
use App\Http\Controllers\Api\ResultadoController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| API Routes -- Sistema CUP FICCT
| --------------------------------------------------------------------------
| Prefijos:
|   /api/public/*    -> Sin autenticacion (postulante)
|   /api/auth/*      -> Login y recuperacion de contrasena
|   /api/admin/*     -> Solo ADMINISTRADOR
|   /api/encargado/* -> ADMINISTRADOR, ENCARGADO
|   /api/coordinador/* (Ciclo 2) -> ADMINISTRADOR, COORDINADOR
|   /api/docente/*     (Ciclo 2) -> ADMINISTRADOR, DOCENTE
|
| Autenticacion: cookies de Sanctum (SPA). Cada request del SPA pasa por
| /sanctum/csrf-cookie antes de un POST.
*/

// =========================
// PUBLIC (sin login)
// =========================
Route::get('/ping', PingController::class);

Route::prefix('public')->group(function () {
    Route::get('/carreras',       [CatalogoController::class, 'carreras']);
    Route::get('/materias',       [CatalogoController::class, 'materias']);
    Route::get('/turnos',         [CatalogoController::class, 'turnos']);
    Route::get('/requisitos',     [CatalogoController::class, 'requisitos']);
    Route::get('/gestion-activa', [CatalogoController::class, 'gestionActiva']);

    // Preinscripcion (CU5, CU6)
    Route::post('/preinscripciones',                 [PreinscripcionController::class, 'store']);
    Route::post('/preinscripciones/buscar',          [PreinscripcionController::class, 'buscarPorDocumento']);
    Route::get('/preinscripciones/{postulacion}',    [PreinscripcionController::class, 'show']);
    Route::post('/preinscripciones/{postulacion}/generar-formulario',
        [PreinscripcionController::class, 'generarFormulario']);

    // Pago (CU7)
    Route::post('/pagos/iniciar',           [PagoController::class, 'iniciar']);
    Route::post('/pagos/{pago}/confirmar',  [PagoController::class, 'confirmar']);

    // Consulta publica de resultados (preparada en Ciclo 1)
    Route::get('/resultados',  [ResultadoController::class, 'consultar']);
});

// =========================
// AUTH (CU1)
// =========================
Route::prefix('auth')->group(function () {
    Route::post('/login',            [AuthController::class, 'login']);
    Route::post('/forgot-password',  [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',   [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

// =========================
// ZONA AUTENTICADA
// =========================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/dashboard/resumen',    [DashboardController::class, 'resumen']);
    Route::get('/dashboard/gestiones',  [DashboardController::class, 'gestionesParaDashboard']);
    Route::get('/catalogos/ambientes', [CatalogoController::class, 'ambientes']);
    Route::get('/catalogos/turnos',    [CatalogoController::class, 'turnos']);
    Route::get('/catalogos/carreras',  [CatalogoController::class, 'carreras']);
    Route::get('/catalogos/materias',  [CatalogoController::class, 'materias']);

    // ------- ADMINISTRADOR -------
    Route::middleware('role:ADMINISTRADOR')->prefix('admin')->group(function () {
        // Usuarios y roles (CU2)
        Route::get   ('/usuarios',                       [UsuarioController::class, 'index']);
        Route::post  ('/usuarios',                       [UsuarioController::class, 'store']);
        Route::get   ('/usuarios/{user}',                [UsuarioController::class, 'show']);
        Route::put   ('/usuarios/{user}',                [UsuarioController::class, 'update']);
        Route::patch ('/usuarios/{user}/toggle-activo',  [UsuarioController::class, 'toggleActivo']);
        Route::get   ('/roles',                          [RolController::class, 'index']);

        // Gestion CUP (CU3, CU11)
        Route::get  ('/gestiones',                       [GestionCupController::class, 'index']);
        Route::post ('/gestiones',                       [GestionCupController::class, 'store']);
        Route::get  ('/gestiones/{gestion}',             [GestionCupController::class, 'show']);
        Route::put  ('/gestiones/{gestion}',             [GestionCupController::class, 'update']);
        Route::post ('/gestiones/{gestion}/generar-grupos', [GestionCupController::class, 'generarGrupos']);

        // Bitacora basica
        Route::get('/bitacora', [BitacoraController::class, 'index']);
    });

    // ------- ENCARGADO + ADMINISTRADOR -------
    Route::middleware('role:ADMINISTRADOR,ENCARGADO')->prefix('encargado')->group(function () {
        // Postulantes (CU4)
        Route::get  ('/postulaciones',                       [PostulanteController::class, 'index']);
        Route::get  ('/postulaciones/{postulacion}',         [PostulanteController::class, 'show']);
        Route::patch('/personas/{persona}',                  [PostulanteController::class, 'updatePersona']);
        Route::post ('/postulaciones/{postulacion}/anular',  [PostulanteController::class, 'anular']);

        // Verificacion de requisitos (CU8)
        Route::get  ('/postulaciones/{postulacion}/requisitos',
            [RequisitoVerificacionController::class, 'index']);
        Route::patch('/postulacion-requisitos/{pr}',
            [RequisitoVerificacionController::class, 'marcar']);
        Route::get  ('/postulaciones/{postulacion}/requisitos/completados',
            [RequisitoVerificacionController::class, 'completados']);

        // Confirmacion de inscripcion (CU9) + boleta (CU10)
        Route::post('/postulaciones/{postulacion}/confirmar', [InscripcionController::class, 'confirmar']);
        Route::get ('/postulaciones/{postulacion}/boleta',    [InscripcionController::class, 'boleta']);

        // Grupos
        Route::get  ('/grupos',          [GrupoController::class, 'index']);
        Route::patch('/grupos/{grupo}',  [GrupoController::class, 'update']);
    });

    // ------- DOCENTE / COORDINADOR (estructura preparada para Ciclo 2) -------
    Route::middleware('role:ADMINISTRADOR,DOCENTE')->prefix('docente')->group(function () {
        Route::get('/_placeholder', fn () => response()->json(['ciclo' => 2, 'modulo' => 'docente']));
    });

    Route::middleware('role:ADMINISTRADOR,COORDINADOR')->prefix('coordinador')->group(function () {
        Route::get('/_placeholder', fn () => response()->json(['ciclo' => 2, 'modulo' => 'coordinador']));
    });

    // ------- VISTA DE RESULTADOS (ADMINISTRADOR + COORDINADOR) -------
    // Ciclo 2: lista de resultados con filtros y KPIs.
    Route::middleware('role:ADMINISTRADOR,COORDINADOR')->prefix('resultados')->group(function () {
        Route::get('/',          [ResultadoAdminController::class, 'index']);
        Route::get('/kpis',      [ResultadoAdminController::class, 'kpis']);
        // Listado de gestiones (para llenar el filtro)
        Route::get('/gestiones', function () {
            return response()->json(
                \App\Models\GestionCup::orderByDesc('id')
                    ->select('id', 'codigo', 'nombre', 'estado')
                    ->get()
            );
        });
    });
});
