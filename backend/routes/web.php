<?php

use Illuminate\Support\Facades\Route;

// El backend es solo una API: la interfaz está en el sitio estático (frontend/).
Route::get('/', function () {
    return response()->json([
        'api' => 'MENTE Arequipa',
        'estado' => 'activa',
        'repositorio' => 'https://github.com/kandyvm/MenteArequipa',
    ]);
});
