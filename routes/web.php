<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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