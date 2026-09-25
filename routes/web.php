<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PurchaseController;

// RUTA MAESTRA: Te redirecciona de inmediato al módulo de compras avanzado rosa
Route::get('/', function () {
    return redirect()->route('purchases.index');
});

// PUENTE DE NAVEGACIÓN: Evita que el menú superior se bloquee buscando el tablero
Route::get('/dashboard', function () {
    return redirect()->route('purchases.index');
})->name('dashboard');

// Cable 1: El cargador robotizado del XML de la DIAN (En singular impecable)
Route::post('/purchases/import-xml', [PurchaseController::class, 'importXML'])->name('purchases.import-xml');

// Cable 2: El módulo general de gestión de compras avanzado rosa
Route::resource('purchases', PurchaseController::class);
Route::get('/compras-rosa', fn () => redirect()->route('purchases.index'));