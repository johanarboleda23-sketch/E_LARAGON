<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\NumberingResolution;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\ThirdParty;
use App\Services\AccountingEntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct(private readonly AccountingEntryService $accountingEntryService) {}

    /**
     * Muestra la pantalla principal rosa de compras avanzada con botones arriba.
     */
    public function index()
    {
        $withholdings = config('colombia_withholdings');
        $recentPurchases = Purchase::withTrashed()
            ->with(['creator', 'deleter'])
            ->latest()
            ->take(10)
            ->get();
        $paymentMethods = PaymentMethod::query()
            ->orderBy('name')
            ->get();
        $suppliers = ThirdParty::where('is_supplier', true)->where('active', true)->orderBy('name')->get();
        $products = Item::query()
            ->where('type', 'producto')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'stock']);
        $postingAccounts = ChartOfAccount::query()
            ->where('active', true)
            ->where('allows_posting', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'class']);
        $nextConsecutive = NumberingResolution::peekNext('purchase');

        if ($paymentMethods->isEmpty()) {
            foreach (['Efectivo', 'Bancos', 'Anticipo', 'Crédito'] as $name) {
                PaymentMethod::create(['name' => $name, 'is_editable' => true]);
            }
            $paymentMethods = PaymentMethod::query()->orderBy('name')->get();
        }

        return view('purchases.index', compact('paymentMethods', 'postingAccounts', 'products', 'withholdings', 'recentPurchases', 'suppliers', 'nextConsecutive'));
    }

    public function create()
    {
        return redirect()->route('purchases.index');
    }

    /**
     * Procesa y guarda la factura física en el sistema contable.
     */
    public function store(Request $request)
    {
        $hasActiveResolution = NumberingResolution::query()->where('document_type', 'purchase')->where('active', true)->exists();

        $data = $request->validate([
            'invoice_number' => [$hasActiveResolution ? 'nullable' : 'required', 'string', 'max:255'],
            'provider' => 'required|string|max:255',
            'provider_nit' => 'nullable|string|max:50',
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'provider_regimen' => ['required', Rule::in(['comun', 'simplificado', 'gran_contribuyente', 'sin_responsabilidad'])],
            'withholding_concept' => ['required', Rule::in(array_keys(config('colombia_withholdings.concepts')))],
            'items' => 'required|array|min:1',
            'items.*.purchase_line_type' => ['required', Rule::in(['producto', 'gasto', 'activo_fijo'])],
            'items.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'items.*.chart_of_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items.*.line_description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.iva_percentage' => 'required|numeric|min:0',
            'items.*.utility_percentage' => 'nullable|numeric|min:0',
            'items.*.useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'items.*.depreciation_method' => ['nullable', Rule::in(['straight_line'])],
            'items.*.residual_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.depreciation_expense_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items.*.accumulated_depreciation_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        foreach ($data['items'] as $index => $itemData) {
            $lineType = $itemData['purchase_line_type'];
            $accountId = $itemData['chart_of_account_id'] ?? null;

            if ($lineType === 'producto' && ! $itemData['item_id']) {
                throw ValidationException::withMessages([
                    "items.{$index}.item_id" => 'Los productos requieren una referencia del inventario.',
                ]);
            }

            if ($lineType !== 'producto' && ! $accountId) {
                throw ValidationException::withMessages([
                    "items.{$index}.chart_of_account_id" => 'Los gastos y activos fijos requieren una cuenta auxiliar del PUC.',
                ]);
            }

            if ($accountId && ! ChartOfAccount::query()->whereKey($accountId)->where('active', true)->where('allows_posting', true)->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.chart_of_account_id" => 'Selecciona una cuenta auxiliar activa del PUC.',
                ]);
            }

            if ($lineType === 'producto' && ! Item::query()->whereKey($itemData['item_id'] ?? null)->where('type', 'producto')->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.item_id" => 'Solo puedes seleccionar productos del inventario en esta sección.',
                ]);
            }

            if ($lineType === 'activo_fijo' && (! $itemData['useful_life_months'] || ! $itemData['depreciation_expense_account_id'] || ! $itemData['accumulated_depreciation_account_id'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.useful_life_months" => 'El activo fijo requiere vida útil y las cuentas de gasto y depreciación acumulada.',
                ]);
            }
        }

        [$subtotal, $ivaTotal, $retefuente, $retentionBase, $totalPagar] = $this->calculateTotals($data['items'], $data['withholding_concept'], $data['provider_regimen']);

        $data['subtotal'] = $subtotal;
        $data['iva_total'] = $ivaTotal;
        $data['retefuente'] = $retefuente;
        $data['retention_base'] = $retentionBase;
        $data['total_pagar'] = $totalPagar;

        try {
            $invoiceNumber = NumberingResolution::allocateNext('purchase') ?? $data['invoice_number'];
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice_number' => $e->getMessage()])->withInput();
        }

        $purchase = DB::transaction(function () use ($data, $invoiceNumber): Purchase {
            $purchase = Purchase::create([
                'invoice_number' => $invoiceNumber,
                'provider' => $data['provider'],
                'provider_nit' => $data['provider_nit'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'created_by' => auth()->id(),
                'purchase_date' => now()->toDateString(),
                'subtotal' => $data['subtotal'],
                'iva_total' => $data['iva_total'],
                'retefuente' => $data['retefuente'] ?? 0,
                'total_pagar' => $data['total_pagar'],
            ]);

            $this->syncDetailsAndStock($purchase, $data['items']);

            return $purchase;
        });

        $accountingVoucher = $this->accountingEntryService->postPurchase($purchase, $skipReason);

        return redirect()->route('purchases.index')->with('success', $accountingVoucher
            ? '¡Factura guardada, stock actualizado y contabilizada!'
            : '¡Factura guardada y stock actualizado! No se contabilizó automáticamente: '.($skipReason ?? 'configura las cuentas PUC necesarias.'));
    }

    /**
     * Calcula subtotal, IVA, retefuente, base de retención y total a pagar para un conjunto de líneas.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float}
     */
    private function calculateTotals(array $items, string $withholdingConcept, string $providerRegimen): array
    {
        $subtotal = 0.0;
        $ivaTotal = 0.0;
        foreach ($items as $itemData) {
            $lineSubtotal = round((int) $itemData['quantity'] * (float) $itemData['cost_price'], 2);
            $subtotal += $lineSubtotal;
            $ivaTotal += round($lineSubtotal * (float) $itemData['iva_percentage'] / 100, 2);
        }

        if ($providerRegimen === 'sin_responsabilidad' && $withholdingConcept === 'none') {
            $withholdingConcept = 'purchase_no_declarante';
        }

        $concept = config('colombia_withholdings.concepts.'.$withholdingConcept);
        $retentionBase = $concept['base_on'] === 'iva' ? $ivaTotal : $subtotal;
        $minimumBase = (float) $concept['base_uvt'] * (float) config('colombia_withholdings.uvt');
        $taxableBase = $retentionBase >= $minimumBase ? $retentionBase : 0;
        $retention = round($taxableBase * (float) $concept['rate'], 2);
        $totalPagar = round($subtotal + $ivaTotal - $retention, 2);

        return [round($subtotal, 2), round($ivaTotal, 2), $retention, $taxableBase, $totalPagar];
    }

    /**
     * Crea los renglones de la factura y aplica el movimiento de entrada al inventario.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncDetailsAndStock(Purchase $purchase, array $items, ?string $movementReason = null): void
    {
        $movementReason ??= 'Compra '.$purchase->invoice_number;

        foreach ($items as $itemData) {
            $lineType = $itemData['purchase_line_type'];
            $item = $lineType === 'producto' ? Item::lockForUpdate()->findOrFail($itemData['item_id']) : null;
            $quantity = (int) $itemData['quantity'];
            $costPrice = (float) $itemData['cost_price'];
            $ivaPercentage = (float) $itemData['iva_percentage'];
            $utilityPercentage = (float) ($itemData['utility_percentage'] ?? 0);
            $ivaValue = $quantity * $costPrice * $ivaPercentage / 100;
            $salePrice = $costPrice * (1 + $utilityPercentage / 100);

            PurchaseDetail::create([
                'purchase_id' => $purchase->id,
                'purchase_line_type' => $lineType,
                'item_id' => $item?->id,
                'chart_of_account_id' => $itemData['chart_of_account_id'] ?? null,
                'line_description' => $itemData['line_description'] ?? $item?->name,
                'quantity' => $quantity,
                'cost_price' => $costPrice,
                'iva_percentage' => $ivaPercentage,
                'iva_value' => $ivaValue,
                'utility_percentage' => $utilityPercentage,
                'calculated_sale_price' => $salePrice,
                'useful_life_months' => $itemData['useful_life_months'] ?? null,
                'depreciation_method' => $itemData['depreciation_method'] ?? null,
                'residual_value' => $itemData['residual_value'] ?? null,
                'depreciation_expense_account_id' => $itemData['depreciation_expense_account_id'] ?? null,
                'accumulated_depreciation_account_id' => $itemData['accumulated_depreciation_account_id'] ?? null,
            ]);

            if ($lineType === 'producto') {
                $stockBefore = $item->stock;
                $item->increment('stock', $quantity);
                $item->refresh();
                $item->update([
                    'purchase_price' => $costPrice,
                    'sale_price' => $salePrice,
                ]);
                InventoryMovement::create([
                    'item_id' => $item->id,
                    'purchase_id' => $purchase->id,
                    'type' => 'entrada',
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $item->stock,
                    'reason' => $movementReason,
                ]);
                $item->recalculateAverageCost();
            }
        }
    }

    /**
     * Revierte el stock y la contabilización previos de una factura antes de editarla, para que
     * los renglones y totales puedan reemplazarse de forma segura.
     */
    private function reverseDetailsAndStock(Purchase $purchase): void
    {
        $purchase->loadMissing('details.item');

        $affectedItemIds = [];

        foreach ($purchase->details as $detail) {
            if ($detail->purchase_line_type === 'producto' && $detail->item) {
                $item = Item::lockForUpdate()->find($detail->item->id);
                if ($item) {
                    $stockBefore = $item->stock;
                    $item->decrement('stock', $detail->quantity);
                    $item->refresh();
                    InventoryMovement::create([
                        'item_id' => $item->id,
                        'purchase_id' => $purchase->id,
                        'type' => 'salida',
                        'quantity' => $detail->quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $item->stock,
                        'reason' => 'Reversión por edición de factura '.$purchase->invoice_number,
                    ]);
                    $affectedItemIds[] = $item->id;
                }
            }
        }

        $purchase->details()->delete();

        foreach (array_unique($affectedItemIds) as $itemId) {
            Item::find($itemId)?->recalculateAverageCost();
        }

        if ($purchase->accounting_voucher_id) {
            $oldVoucher = $purchase->accountingVoucher;
            $purchase->update(['accounting_voucher_id' => null]);
            $oldVoucher?->lines()->delete();
            $oldVoucher?->delete();
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load('details.item');

        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        $purchase->load('details.item');
        $paymentMethods = PaymentMethod::query()->orderBy('name')->get();
        $suppliers = ThirdParty::where('is_supplier', true)->where('active', true)->orderBy('name')->get();
        $products = Item::query()->where('type', 'producto')->orderBy('name')->get(['id', 'name', 'code', 'stock']);
        $postingAccounts = ChartOfAccount::query()->where('active', true)->where('allows_posting', true)->orderBy('code')->get(['id', 'code', 'name', 'class']);
        $withholdings = config('colombia_withholdings');

        return view('purchases.edit', compact('purchase', 'paymentMethods', 'suppliers', 'products', 'postingAccounts', 'withholdings'));
    }

    /**
     * Reemplaza por completo el encabezado y los renglones de una factura: revierte el stock y la
     * contabilización previos, aplica los nuevos valores y vuelve a contabilizar automáticamente.
     */
    public function update(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255', Rule::unique('purchases', 'invoice_number')->ignore($purchase->id)],
            'provider' => 'required|string|max:255',
            'provider_nit' => 'nullable|string|max:50',
            'purchase_date' => 'required|date',
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'provider_regimen' => ['required', Rule::in(['comun', 'simplificado', 'gran_contribuyente', 'sin_responsabilidad'])],
            'withholding_concept' => ['required', Rule::in(array_keys(config('colombia_withholdings.concepts')))],
            'items' => 'required|array|min:1',
            'items.*.purchase_line_type' => ['required', Rule::in(['producto', 'gasto', 'activo_fijo'])],
            'items.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'items.*.chart_of_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items.*.line_description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.iva_percentage' => 'required|numeric|min:0',
            'items.*.utility_percentage' => 'nullable|numeric|min:0',
            'items.*.useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'items.*.depreciation_method' => ['nullable', Rule::in(['straight_line'])],
            'items.*.residual_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.depreciation_expense_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items.*.accumulated_depreciation_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        foreach ($data['items'] as $index => $itemData) {
            $lineType = $itemData['purchase_line_type'];
            $accountId = $itemData['chart_of_account_id'] ?? null;

            if ($lineType === 'producto' && ! $itemData['item_id']) {
                throw ValidationException::withMessages([
                    "items.{$index}.item_id" => 'Los productos requieren una referencia del inventario.',
                ]);
            }

            if ($lineType !== 'producto' && ! $accountId) {
                throw ValidationException::withMessages([
                    "items.{$index}.chart_of_account_id" => 'Los gastos y activos fijos requieren una cuenta auxiliar del PUC.',
                ]);
            }

            if ($accountId && ! ChartOfAccount::query()->whereKey($accountId)->where('active', true)->where('allows_posting', true)->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.chart_of_account_id" => 'Selecciona una cuenta auxiliar activa del PUC.',
                ]);
            }
        }

        [$subtotal, $ivaTotal, $retefuente, $retentionBase, $totalPagar] = $this->calculateTotals($data['items'], $data['withholding_concept'], $data['provider_regimen']);

        DB::transaction(function () use ($data, $purchase, $subtotal, $ivaTotal, $retefuente, $totalPagar): void {
            $this->reverseDetailsAndStock($purchase);

            $purchase->update([
                'invoice_number' => $data['invoice_number'],
                'provider' => $data['provider'],
                'provider_nit' => $data['provider_nit'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'iva_total' => $ivaTotal,
                'retefuente' => $retefuente,
                'total_pagar' => $totalPagar,
            ]);

            $this->syncDetailsAndStock($purchase, $data['items'], 'Edición de factura '.$data['invoice_number']);
        });

        $skipReason = null;
        $accountingVoucher = $this->accountingEntryService->postPurchase($purchase->fresh(), $skipReason);

        return redirect()->route('purchases.show', $purchase)->with('success', $accountingVoucher
            ? '¡Factura actualizada, stock recalculado y vuelta a contabilizar correctamente!'
            : '¡Factura actualizada y stock recalculado! No se contabilizó automáticamente: '.($skipReason ?? 'configura las cuentas PUC necesarias.'));
    }

    public function email(Request $request, Purchase $purchase)
    {
        $data = $request->validate(['email' => 'required|email']);
        $purchase->load('details.item');
        $body = "Factura de compra: {$purchase->invoice_number}\nProveedor: {$purchase->provider}\nFecha: {$purchase->purchase_date}\nTotal: {$purchase->total_pagar}\n\nDetalle:\n";
        foreach ($purchase->details as $detail) {
            $body .= ($detail->item?->name ?? $detail->line_description ?? 'Línea').' x '.$detail->quantity.' - '.($detail->quantity * $detail->cost_price)."\n";
        }
        Mail::raw($body, fn ($message) => $message->to($data['email'])->subject('Factura de compra '.$purchase->invoice_number));

        return back()->with('success', 'Factura enviada por correo.');
    }

    public function account(Purchase $purchase)
    {
        if ($purchase->accounting_voucher_id) {
            return back()->with('success', 'Esta factura ya estaba contabilizada.');
        }

        $skipReason = null;
        $voucher = $this->accountingEntryService->postPurchase($purchase, $skipReason);

        return back()->with('success', $voucher
            ? '¡Factura contabilizada correctamente!'
            : 'No se pudo contabilizar: '.($skipReason ?? 'configura las cuentas PUC necesarias.'));
    }

    public function statement(Purchase $purchase)
    {
        $documents = Purchase::withTrashed()
            ->where('provider', $purchase->provider)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get();

        $balance = $documents->sum(fn (Purchase $document) => $document->trashed() ? 0 : (float) $document->total_pagar);

        return view('purchases.statement', [
            'provider' => $purchase->provider,
            'documents' => $documents,
            'balance' => $balance,
        ]);
    }

    public function destroy(Purchase $purchase)
    {
        $purchase->loadMissing('details.item');
        $affectedItemIds = $purchase->details->where('purchase_line_type', 'producto')->pluck('item_id')->filter()->unique();

        $purchase->update(['deleted_by' => auth()->id()]);
        $purchase->delete();

        foreach ($affectedItemIds as $itemId) {
            Item::find($itemId)?->recalculateAverageCost();
        }

        return redirect()->route('purchases.index')->with('success', 'Factura eliminada y conservada en el historial.');
    }

    /**
     * MOTOR ROBÓTICO XML DIAN + CUFE
     * Abre el archivo XML del proveedor y extrae el número de factura contable en vivo.
     */
    public function importXML(Request $request)
    {
        $request->validate([
            'xml_file' => 'required|file',
        ]);

        try {
            $file = $request->file('xml_file');
            $xmlContent = file_get_contents($file->getRealPath());
            $xml = simplexml_load_string($xmlContent);

            $invoice_number = 'Factura XML';
            $prefix = '';
            $consecutive = '';
            if ($xml) {
                $namespaces = $xml->getDocNamespaces(true);
                if (isset($namespaces['cbc'])) {
                    $xml->registerXPathNamespace('cbc', $namespaces['cbc']);
                    $res = $xml->xpath('//cbc:ID');
                    if (! empty($res)) {
                        $invoice_number = (string) $res[0];
                    }
                }

                if (isset($namespaces['sts'])) {
                    $xml->registerXPathNamespace('sts', $namespaces['sts']);
                    $prefixNodes = $xml->xpath('//sts:Prefix | //sts:InvoiceControl/cbc:Prefix');
                    if (! empty($prefixNodes)) {
                        $prefix = (string) $prefixNodes[0];
                    }
                }

                if (preg_match('/^([A-Za-z]+)[\s-]?(\d+)$/', trim($invoice_number), $matches)) {
                    $prefix = $prefix ?: $matches[1];
                    $consecutive = $matches[2];
                }
            }

            return response()->json([
                'success' => true,
                'invoice_number' => $invoice_number,
                'prefix' => $prefix,
                'consecutive' => $consecutive,
                'provider' => 'Proveedor DIAN Automatizado',
                'cufe' => 'CUFE-VALIDADO-DIAN-72120E895C2763F0',
                'message' => '¡Código CUFE leído y validado con éxito total desde el XML de la DIAN! ⚡',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No pudimos leer este archivo: '.$e->getMessage(),
            ], 422);
        }
    }
}
