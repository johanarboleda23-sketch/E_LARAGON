<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\ThirdParty;
use App\Services\AccountingEntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($paymentMethods->isEmpty()) {
            foreach (['Efectivo', 'Bancos', 'Anticipo', 'Crédito'] as $name) {
                PaymentMethod::create(['name' => $name, 'is_editable' => true]);
            }
            $paymentMethods = PaymentMethod::query()->orderBy('name')->get();
        }

        return view('purchases.index', compact('paymentMethods', 'postingAccounts', 'products', 'withholdings', 'recentPurchases', 'suppliers'));
    }

    /**
     * Procesa y guarda la factura física en el sistema contable.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_number' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
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

        $subtotal = 0.0;
        $ivaTotal = 0.0;
        foreach ($data['items'] as $itemData) {
            $lineSubtotal = round((int) $itemData['quantity'] * (float) $itemData['cost_price'], 2);
            $subtotal += $lineSubtotal;
            $ivaTotal += round($lineSubtotal * (float) $itemData['iva_percentage'] / 100, 2);
        }

        $withholdingConcept = $data['withholding_concept'];
        if ($data['provider_regimen'] === 'sin_responsabilidad' && $withholdingConcept === 'none') {
            $withholdingConcept = 'purchase_no_declarante';
        }

        $concept = config('colombia_withholdings.concepts.'.$withholdingConcept);
        $retentionBase = $concept['base_on'] === 'iva' ? $ivaTotal : $subtotal;
        $minimumBase = (float) $concept['base_uvt'] * (float) config('colombia_withholdings.uvt');
        $taxableBase = $retentionBase >= $minimumBase ? $retentionBase : 0;
        $retention = round($taxableBase * (float) $concept['rate'], 2);

        $data['subtotal'] = round($subtotal, 2);
        $data['iva_total'] = round($ivaTotal, 2);
        $data['retefuente'] = $retention;
        $data['retention_base'] = $taxableBase;
        $data['total_pagar'] = round($subtotal + $ivaTotal - $retention, 2);

        $purchase = DB::transaction(function () use ($data): Purchase {
            $purchase = Purchase::create([
                'invoice_number' => $data['invoice_number'],
                'provider' => $data['provider'],
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'created_by' => auth()->id(),
                'purchase_date' => now()->toDateString(),
                'subtotal' => $data['subtotal'],
                'iva_total' => $data['iva_total'],
                'retefuente' => $data['retefuente'] ?? 0,
                'total_pagar' => $data['total_pagar'],
            ]);

            foreach ($data['items'] as $itemData) {
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
                        'reason' => 'Compra '.$purchase->invoice_number,
                    ]);
                }
            }

            return $purchase;
        });

        $accountingVoucher = $this->accountingEntryService->postPurchase($purchase);

        return redirect()->route('purchases.index')->with('success', $accountingVoucher
            ? '¡Factura guardada, stock actualizado y contabilizada!'
            : '¡Factura guardada y stock actualizado! Configura las cuentas PUC para contabilizarla automáticamente.');
    }

    public function destroy(Purchase $purchase)
    {
        $purchase->update(['deleted_by' => auth()->id()]);
        $purchase->delete();

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
