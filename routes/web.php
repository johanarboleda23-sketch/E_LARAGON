    // NUEVA RUTA INTEGRADA DEL BUSCADOR DIRECTO EN EL DASHBOARD
    Route::get('/comprobantes/buscar-directo', [BuscadorDirectoController::class, 'redirigirComprobante'])->name('comprobantes.buscar.directo');
});

// Incluir rutas de autenticación por defecto (Laravel Breeze/Jetstream)
if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}
