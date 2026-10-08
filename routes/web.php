<?php

use App\Http\Controllers\AccountingVoucherController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BulkOperationController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CommercialDocumentController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DocumentLookupController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\NumberingResolutionController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RegulationController;
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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'company'])->group(function () {
    Route::middleware('module:commercial-documents')->group(function () {
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
    });

    Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reportes/excel', [ReportController::class, 'excel'])->name('reports.excel');
    Route::get('/reportes/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/normativa', [RegulationController::class, 'index'])->name('regulations.index');

    Route::middleware('module:purchases')->group(function () {
        Route::post('/purchases/import-xml', [PurchaseController::class, 'importXML'])->name('purchases.import-xml');
        Route::resource('purchases', PurchaseController::class);
    });

    Route::middleware('module:sales')->group(function () {
        Route::get('/ventas', [SaleController::class, 'index'])->name('sales.index');
        Route::post('/ventas', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/ventas/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::post('/ventas/{sale}/correo', [SaleController::class, 'email'])->name('sales.email');
        Route::get('/ventas/{sale}/xml', [SaleController::class, 'xml'])->name('sales.xml');
    });

    Route::middleware('module:support-documents')->group(function () {
        Route::get('/documentos-soporte', [SupportDocumentController::class, 'index'])->name('support-documents.index');
        Route::post('/documentos-soporte', [SupportDocumentController::class, 'store'])->name('support-documents.store');
        Route::get('/documentos-soporte/{document}', [SupportDocumentController::class, 'show'])->name('support-documents.show');
        Route::get('/documentos-soporte/{document}/xml', [SupportDocumentController::class, 'xml'])->name('support-documents.xml');
        Route::post('/documentos-soporte/{document}/correo', [SupportDocumentController::class, 'email'])->name('support-documents.email');
    });

    Route::middleware('module:payroll')->group(function () {
        Route::get('/nomina', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/nomina/calcular', [PayrollController::class, 'calculate'])->name('payroll.calculate');
        Route::post('/nomina/{line}/correo', [PayrollController::class, 'email'])->name('payroll.email');
        Route::get('/nomina/{run}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('/nomina/{run}/seguridad-social', [PayrollController::class, 'socialSecurityFile'])->name('payroll.social-security.file');
        Route::post('/nomina/{run}/errores-seguridad-social/importar', [PayrollController::class, 'importSocialSecurityErrors'])->name('payroll.social-security.errors.import');
    });

    Route::middleware('module:third-parties')->group(function () {
        Route::get('/terceros', [ThirdPartyController::class, 'index'])->name('third-parties.index');
        Route::post('/terceros', [ThirdPartyController::class, 'store'])->name('third-parties.store');
        Route::patch('/terceros/{thirdParty}/estado', [ThirdPartyController::class, 'toggleActive'])->name('third-parties.toggle-active');
        Route::post('/contratos-empleado', [ThirdPartyController::class, 'storeContract'])->name('employee-contracts.store');
        Route::patch('/contratos-empleado/{contract}/estado', [ThirdPartyController::class, 'toggleContract'])->name('employee-contracts.toggle-active');
    });

    Route::middleware('module:puc')->group(function () {
        Route::get('/contabilidad/puc', [ChartOfAccountController::class, 'index'])->name('accounting.puc.index');
        Route::post('/contabilidad/puc', [ChartOfAccountController::class, 'store'])->name('accounting.puc.store');
        Route::put('/contabilidad/puc/{account}', [ChartOfAccountController::class, 'update'])->name('accounting.puc.update');
        Route::post('/contabilidad/puc/importar', [ChartOfAccountController::class, 'import'])->name('accounting.puc.import');
    });

    Route::middleware('module:logistics')->group(function () {
        Route::get('/logistica', [LogisticsController::class, 'index'])->name('logistics.index');
        Route::post('/logistica/rutas', [LogisticsController::class, 'store'])->name('logistics.store');
        Route::get('/logistica/rutas/{route}', [LogisticsController::class, 'show'])->name('logistics.show');
        Route::post('/logistica/rutas/{route}/planificar', [LogisticsController::class, 'plan'])->name('logistics.plan');
        Route::get('/logistica/rutas/{route}/manifiesto', [LogisticsController::class, 'manifest'])->name('logistics.manifest');
        Route::post('/logistica/rutas/{route}/paradas/importar', [LogisticsController::class, 'importStops'])->name('logistics.stops.import');
        Route::post('/logistica/rutas/{route}/paradas', [LogisticsController::class, 'storeStop'])->name('logistics.stops.store');
        Route::post('/logistica/paradas/{stop}/entregar', [LogisticsController::class, 'deliverStop'])->name('logistics.stops.deliver');
    });

    Route::middleware('module:bank-reconciliation')->group(function () {
        Route::get('/conciliacion-bancaria', [BankReconciliationController::class, 'index'])->name('bank-reconciliation.index');
        Route::post('/conciliacion-bancaria/importar', [BankReconciliationController::class, 'import'])->name('bank-reconciliation.import');
        Route::get('/conciliacion-bancaria/{statement}/sugerencias', [BankReconciliationController::class, 'suggestions'])->name('bank-reconciliation.suggestions');
        Route::post('/conciliacion-bancaria/{statement}/conciliar', [BankReconciliationController::class, 'reconcile'])->name('bank-reconciliation.reconcile');
        Route::post('/conciliacion-bancaria/{statement}/desconciliar', [BankReconciliationController::class, 'unmatch'])->name('bank-reconciliation.unmatch');
    });

    Route::middleware('module:bulk-operations')->group(function () {
        Route::get('/operaciones-masivas', [BulkOperationController::class, 'index'])->name('bulk-operations.index');
        Route::post('/operaciones-masivas/imprimir', [BulkOperationController::class, 'print'])->name('bulk-operations.print');
        Route::post('/operaciones-masivas/descargar', [BulkOperationController::class, 'download'])->name('bulk-operations.download');
        Route::post('/operaciones-masivas/correo', [BulkOperationController::class, 'email'])->name('bulk-operations.email');
    });

    Route::middleware('module:numbering-resolutions')->group(function () {
        Route::resource('numbering-resolutions', NumberingResolutionController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/companies', [AdminController::class, 'storeCompany'])->name('admin.companies.store');
    Route::post('/admin/members', [AdminController::class, 'addMember'])->name('admin.members.store');
    Route::put('/admin/members/{user}/role', [AdminController::class, 'updateRole'])->name('admin.members.role');
    Route::put('/admin/payment-methods/{paymentMethod}', [AdminController::class, 'updatePaymentMethod'])->name('admin.payment-methods.puc');
    Route::post('/admin/puc/seed', [AdminController::class, 'seedChartOfAccounts'])->name('admin.puc.seed');
    Route::post('/admin/factus', [AdminController::class, 'updateFactusCredential'])->name('admin.factus.update');

    Route::middleware('module:pos')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    });

    Route::middleware('module:items')->group(function () {
        Route::resource('items', ItemController::class)->except('show');
        Route::post('/items/{item}/stock', [ItemController::class, 'adjustStock'])->name('items.stock');
    });

    Route::get('/comprobantes/{voucher}/json', [AccountingVoucherController::class, 'json'])->name('accounting.vouchers.json');
    Route::get('/buscar-comprobante', [DocumentLookupController::class, 'find'])->name('documents.lookup');
    Route::get('/buscar-comprobantes', [DocumentLookupController::class, 'search'])->middleware('auth')->name('documents.search');

    Route::middleware('module:accounting-vouchers')->group(function () {
        Route::get('/comprobantes', [AccountingVoucherController::class, 'index'])->name('accounting.vouchers.index');
        Route::post('/comprobantes', [AccountingVoucherController::class, 'store'])->name('accounting.vouchers.store');
        Route::delete('/comprobantes/{voucher}', [AccountingVoucherController::class, 'destroy'])->name('accounting.vouchers.destroy');
        Route::get('/comprobantes/{voucher}/contabilizacion', [AccountingVoucherController::class, 'accounting'])->name('accounting.vouchers.accounting');
        Route::get('/comprobantes/{voucher}/estado-cuenta', [AccountingVoucherController::class, 'statement'])->name('accounting.vouchers.statement');
        Route::post('/comprobantes/{voucher}/correo', [AccountingVoucherController::class, 'email'])->name('accounting.vouchers.email');
    });
});

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}
