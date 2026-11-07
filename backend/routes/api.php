<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DerivacionController;
use App\Http\Controllers\SolicitudController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/registro', [AuthController::class, 'registro']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/yo', [AuthController::class, 'yo']);

    Route::apiResource('solicitudes', SolicitudController::class)
        ->parameters(['solicitudes' => 'solicitud']);

    Route::post('/solicitudes/{solicitud}/derivaciones', [DerivacionController::class, 'store']);

    Route::apiResource('derivaciones', DerivacionController::class)
        ->only(['show', 'update', 'destroy'])
        ->parameters(['derivaciones' => 'derivacion']);
});