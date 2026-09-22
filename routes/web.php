<?php

use App\Http\Controllers\PurchaseController;

Route::middleware(['auth'])->group(function () {
    Route::get('/compras', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/compras/crear', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/compras', [PurchaseController::class, 'store'])->name('purchases.store');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
use App\Http\Controllers\ItemController;

Route::middleware(['auth'])->group(function () {
    Route::get('/inventario', [ItemController::class, 'index'])->name('items.index');
    Route::get('/inventario/crear', [ItemController::class, 'create'])->name('items.create');
    Route::post('/inventario', [ItemController::class, 'store'])->name('items.store');
});