<?php

use App\Http\Controllers\AccountingVoucherController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BulkOperationController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CommercialDocumentController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupportDocumentController;
use App\Http\Controllers\ThirdPartyController;
use Illuminate\Support\Facades\Route;

// RUTA MAESTRA: abre el tablero de módulos
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// TABLERO PRINCIPAL
Route::get('/dashboard', function () {
    $companies = auth()->check() ? auth()->user()->companies()->where('active', true)->orderBy('name')->get() : collect();

    return view('dashboard', compact('companies'));
})->name('dashboard');

Route::post('/empresa/cambiar', [CompanyController::class, 'switch'])->middleware('auth')->name('company.switch');

Route::middleware(['auth', 'company'])->group(function () {
    Route::get('/cotizaciones', [CommercialDocumentController::class, 'index'])->defaults('documentType', 'quotation')->name('commercial-documents.quotations');
    Route::post('/cotizaciones', [CommercialDocumentController::class, 'store'])->defaults('documentType', 'quotation')->name('commercial-documents.quotations.store');
    Route::get('/ordenes-venta', [CommercialDocumentController::class, 'index'])->defaults('documentType', 'sales_order')->name('commercial-documents.sales-orders');
    Route::post('/ordenes-venta', [CommercialDocumentController::class, 'store'])->defaults('documentType', 'sales_order')->name('commercial-documents.sales-orders.store');
    Route::get('/remisiones', [CommercialDocumentController::class, 'index'])->defaults('documentType', 'remission')->name('commercial-documents.remissions');
    Route::post('/remisiones', [CommercialDocumentController::class, 'store'])->defaults('documentType', 'remission')->name('commercial-documents.remissions.store');
    Route::get('/ordenes-compra', [CommercialDocumentController::class, 'index'])->defaults('documentType', 'purchase_order')->name('commercial-documents.purchase-orders');
    Route::post('/ordenes-compra', [CommercialDocumentController::class, 'store'])->defaults('documentType', 'purchase_order')->name('commercial-documents.purchase-orders.store');
    Route::get('/notas-credito-clientes', [CommercialDocumentController::class, 'index'])->defaults('documentType', 'customer_credit_note')->name('commercial-documents.customer-credit-notes');
    Route::post('/notas-credito-clientes', [CommercialDocumentController::class, 'store'])->defaults('documentType', 'customer_credit_note')->name('commercial-documents.customer-credit-notes.store');
    Route::get('/notas-debito-proveedores', [CommercialDocumentController::class, 'index'])->defaults('documentType', 'supplier_debit_note')->name('commercial-documents.supplier-debit-notes');
    Route::post('/notas-debito-proveedores', [CommercialDocumentController::class, 'store'])->defaults('documentType', 'supplier_debit_note')->name('commercial-documents.supplier-debit-notes.store');
    Route::patch('/documentos-comerciales/{document}/consecutivo', [CommercialDocumentController::class, 'updateConsecutive'])->name('commercial-documents.consecutive');
    Route::post('/documentos-comerciales/{document}/correo', [CommercialDocumentController::class, 'email'])->name('commercial-documents.email');
    Route::post('/documentos-comerciales/{document}/dian', [CommercialDocumentController::class, 'sendToDian'])->name('commercial-documents.dian');
    Route::get('/documentos-comerciales/{document}/estado-cuenta', [CommercialDocumentController::class, 'statement'])->name('commercial-documents.statement');
    Route::post('/documentos-comerciales/{document}/convertir', [CommercialDocumentController::class, 'convert'])->name('commercial-documents.convert');
    Route::get('/documentos-comerciales/{document}/imprimir', [CommercialDocumentController::class, 'print'])->name('commercial-documents.print');
    Route::get('/documentos-comerciales/{document}/descargar', [CommercialDocumentController::class, 'download'])->name('commercial-documents.download');
    Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reportes/excel', [ReportController::class, 'excel'])->name('reports.excel');
    Route::get('/reportes/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    // Cable 1: El cargador robotizado del XML de la DIAN (En singular impecable)
    Route::post('/purchases/import-xml', [PurchaseController::class, 'importXML'])->name('purchases.import-xml');

    // Cable 2: El módulo general de gestión de compras avanzado rosa
    Route::resource('purchases', PurchaseController::class);
    Route::get('/ventas', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/ventas', [SaleController::class, 'store'])->name('sales.store');
    Route::post('/ventas/{sale}/correo', [SaleController::class, 'email'])->name('sales.email');
    Route::get('/ventas/{sale}/xml', [SaleController::class, 'xml'])->name('sales.xml');
    Route::resource('items', ItemController::class)->except('show');
    Route::post('/items/{item}/stock', [ItemController::class, 'adjustStock'])->name('items.stock');
    Route::get('/comprobantes', [AccountingVoucherController::class, 'index'])->name('accounting.vouchers.index');
    Route::post('/comprobantes', [AccountingVoucherController::class, 'store'])->name('accounting.vouchers.store');
    Route::delete('/comprobantes/{voucher}', [AccountingVoucherController::class, 'destroy'])->name('accounting.vouchers.destroy');
    Route::get('/comprobantes/{voucher}/contabilizacion', [AccountingVoucherController::class, 'accounting'])->name('accounting.vouchers.accounting');
    Route::get('/comprobantes/{voucher}/estado-cuenta', [AccountingVoucherController::class, 'statement'])->name('accounting.vouchers.statement');
    Route::post('/comprobantes/{voucher}/correo', [AccountingVoucherController::class, 'email'])->name('accounting.vouchers.email');
    Route::get('/puc', [ChartOfAccountController::class, 'index'])->name('accounting.puc.index');
    Route::post('/puc', [ChartOfAccountController::class, 'store'])->name('accounting.puc.store');
    Route::put('/puc/{account}', [ChartOfAccountController::class, 'update'])->name('accounting.puc.update');
    Route::get('/compras-rosa', fn () => redirect()->route('purchases.index'));
    Route::get('/documento-soporte', [SupportDocumentController::class, 'index'])->name('support-documents.index');
    Route::post('/documento-soporte', [SupportDocumentController::class, 'store'])->name('support-documents.store');
    Route::get('/documento-soporte/{document}/xml', [SupportDocumentController::class, 'xml'])->name('support-documents.xml');
    Route::post('/documento-soporte/{document}/correo', [SupportDocumentController::class, 'email'])->name('support-documents.email');
    Route::get('/terceros', [ThirdPartyController::class, 'index'])->name('third-parties.index');
    Route::post('/terceros', [ThirdPartyController::class, 'store'])->name('third-parties.store');
    Route::put('/terceros/{thirdParty}', [ThirdPartyController::class, 'update'])->name('third-parties.update');
    Route::patch('/terceros/{thirdParty}/estado', [ThirdPartyController::class, 'toggleActive'])->name('third-parties.toggle-active');
    Route::post('/terceros/contratos', [ThirdPartyController::class, 'storeContract'])->name('employee-contracts.store');
    Route::patch('/terceros/contratos/{contract}/estado', [ThirdPartyController::class, 'toggleContract'])->name('employee-contracts.toggle-active');
    Route::get('/nomina', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/nomina/calcular', [PayrollController::class, 'calculate'])->name('payroll.calculate');
    Route::post('/nomina/lineas/{line}/correo', [PayrollController::class, 'email'])->name('payroll.email');
    Route::get('/operaciones-masivas', [BulkOperationController::class, 'index'])->name('bulk-operations.index');
    Route::post('/operaciones-masivas/imprimir', [BulkOperationController::class, 'print'])->name('bulk-operations.print');
    Route::post('/operaciones-masivas/descargar', [BulkOperationController::class, 'download'])->name('bulk-operations.download');
    Route::post('/operaciones-masivas/correo', [BulkOperationController::class, 'email'])->name('bulk-operations.email');
    Route::get('/administracion', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/administracion/empresas', [AdminController::class, 'storeCompany'])->name('admin.companies.store');
    Route::post('/administracion/miembros', [AdminController::class, 'addMember'])->name('admin.members.store');
    Route::put('/administracion/miembros/{user}/rol', [AdminController::class, 'updateRole'])->name('admin.members.role');
    Route::get('/logistica', [LogisticsController::class, 'index'])->name('logistics.index');
    Route::post('/logistica', [LogisticsController::class, 'store'])->name('logistics.store');
    Route::get('/logistica/{route}', [LogisticsController::class, 'show'])->name('logistics.show');
    Route::get('/logistica/{route}/manifiesto', [LogisticsController::class, 'manifest'])->name('logistics.manifest');
    Route::post('/logistica/{route}/planificar', [LogisticsController::class, 'plan'])->name('logistics.plan');
    Route::post('/logistica/{route}/paradas/importar', [LogisticsController::class, 'importStops'])->name('logistics.stops.import');
    Route::post('/logistica/{route}/paradas', [LogisticsController::class, 'storeStop'])->name('logistics.stops.store');
    Route::post('/logistica/paradas/{stop}/entrega', [LogisticsController::class, 'deliverStop'])->name('logistics.stops.deliver');
    Route::get('/logistica/paradas/{stop}/evidencia', [LogisticsController::class, 'evidence'])->name('logistics.stops.evidence');
});

require __DIR__.'/auth.php';
