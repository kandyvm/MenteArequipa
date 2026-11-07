<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CentroController;
use App\Http\Controllers\ConsultaPublicaController;
use App\Http\Controllers\DerivacionController;
use App\Http\Controllers\SolicitudController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/registro', [AuthController::class, 'registro']);

// Consulta pública: sin sesión, 10 por minuto por IP y solo el formato JOV-XXXXX
Route::get('/consulta/{codigo}', [ConsultaPublicaController::class, 'mostrar'])
    ->where('codigo', 'JOV-[A-Z0-9]{5}')
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/yo', [AuthController::class, 'yo']);

    Route::apiResource('solicitudes', SolicitudController::class)
        ->parameters(['solicitudes' => 'solicitud']);

    Route::post('/solicitudes/{solicitud}/derivaciones', [DerivacionController::class, 'store']);

    Route::apiResource('derivaciones', DerivacionController::class)
        ->only(['show', 'update', 'destroy'])
        ->parameters(['derivaciones' => 'derivacion']);

    Route::apiResource('centros', CentroController::class);

    // Auditoría: solo lectura (ADMIN)
    Route::get('/auditoria', [AuditoriaController::class, 'index']);
});