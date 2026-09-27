<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'ensureReportAccess'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/pdf', [ReportController::class, 'generatePdf'])->name('reports.pdf');
    Route::get('/reports/excel', [ReportController::class, 'generateExcel'])->name('reports.excel');
});
