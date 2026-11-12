<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\NumberingResolution;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\ThirdParty;
use App\Services\AccountingEntryService;
use App\Services\FactusService;
use App\Services\InventoryUnitService;
use App\Services\PaymentMethodService;
use App\Services\SalesRetentionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function __construct(
        private readonly AccountingEntryService $accountingEntryService,
        private readonly FactusService $factusService,
        private readonly InventoryUnitService $inventoryUnitService,
        private readonly SalesRetentionCalculator $salesRetentionCalculator,
        private readonly PaymentMethodService $paymentMethodService,
    ) {}

    public function index(Request $request)
    {
        $items = Item::with('unitConversions')->where('type', 'producto')->orderBy('name')->get();
        $sales = Sale::with(['details.item', 'creator', 'paymentMethod', 'payments'])->latest()->take(20)->get();
        $selectedSaleId = $request->integer('preview') ?: $request->session()->get('preview_sale_id');
        $selectedSale = $selectedSaleId
            ? Sale::with(['details.item', 'paymentMethod', 'accountingVoucher', 'payments'])->findOrFail($selectedSaleId)
            : null;
        $paymentMethods = $this->paymentMethodService->available();
        $customers = ThirdParty::where('is_customer', true)->where('active', true)->orderBy('name')->get();
        $withholdings = config('colombia_withholdings');
        $nextConsecutive = NumberingResolution::peekNext('sale');
        $company = Company::findOrFail($request->session()->get('company_id'));
        $nextInternalConsecutive = $this->nextInternalConsecutive($company);
        $productOptions = $items->map(function (Item $item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'price' => $item->sale_price,
                'stock' => $item->stock,
                'base_unit' => $item->base_unit ?: 'unidad',
                'units' => $item->unitConversions->map(fn ($unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'factor' => $unit->factor,
                    'sale_price' => $unit->sale_price,
                ])->values(),
            ];
        })->values();

        return view('sales.index', compact('items', 'sales', 'paymentMethods', 'productOptions', 'withholdings', 'customers', 'nextConsecutive', 'nextInternalConsecutive', 'selectedSale'));
    }

    public function store(Request $request)
    {
        $hasActiveResolution = NumberingResolution::query()->where('document_type', 'sale')->where('active', true)->exists();

        $data = $request->validate([
            'invoice_number' => $hasActiveResolution || $request->boolean('skip_dian')
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255', Rule::unique('sales', 'invoice_number')->where('company_id', session('company_id'))],
            'customer_name' => 'required|string|max:255',
            'customer_document' => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:255',
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')->where('company_id', session('company_id')),
            ],
            'sale_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'iva_total' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'withholding_concept' => ['required', 'string', Rule::in(array_keys(config('colombia_withholdings.sales_concepts')))],
            'retention_base' => 'required|numeric|min:0',
            'retention_total' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_conversion_id' => ['nullable', 'integer', 'exists:item_unit_conversions,id'],
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.iva_percentage' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $retention = $this->salesRetentionCalculator->calculate(
            $data['withholding_concept'],
            (float) $data['subtotal'],
            (float) $data['discount_total'],
            (float) $data['iva_total'],
        );
        $data['retention_base'] = $retention['retention_base'];
        $data['retention_total'] = $retention['retention_total'];
        $data['total'] = round((float) $data['subtotal'] - (float) $data['discount_total'] + (float) $data['iva_total'] - $retention['retention_total'], 2);

        try {
            $sale = DB::transaction(function () use ($data, $request) {
                try {
                    if ($request->boolean('skip_dian')) {
                        $company = Company::query()->lockForUpdate()->findOrFail($request->session()->get('company_id'));
                        $invoiceNumber = $this->nextInternalConsecutive($company);
                        $company->update(['next_internal_sale_number' => (int) $invoiceNumber + 1]);
                    } else {
                        $invoiceNumber = NumberingResolution::allocateNext('sale') ?? $data['invoice_number'];
                    }
                } catch (\RuntimeException $e) {
                    throw ValidationException::withMessages([
                        'invoice_number' => $e->getMessage(),
                    ]);
                }

                $sale = Sale::create([
                    'invoice_number' => $invoiceNumber,
                    'customer_name' => $data['customer_name'],
                    'customer_document' => $data['customer_document'] ?? null,
                    'customer_email' => $data['customer_email'] ?? null,
                    'payment_method_id' => $data['payment_method_id'] ?? null,
                    'sale_date' => $data['sale_date'],
                    'subtotal' => $data['subtotal'],
                    'iva_total' => $data['iva_total'],
                    'discount_total' => $data['discount_total'],
                    'withholding_concept' => $data['withholding_concept'],
                    'retention_base' => $data['retention_base'],
                    'retention_total' => $data['retention_total'],
                    'total' => $data['total'],
                    'skip_dian' => $request->boolean('skip_dian'),
                    'created_by' => auth()->id(),
                ]);

                $this->syncDetailsAndStock($sale, $data['items']);

                return $sale;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $skipReason = null;
        $accountingVoucher = $this->accountingEntryService->postSale($sale, $skipReason);

        $factusSkipReason = null;
        $sentToDian = $sale->skip_dian ? false : $this->factusService->sendSaleInvoice($sale, session('company_id'), $factusSkipReason);

        $message = $accountingVoucher
            ? '¡Factura de venta guardada, stock actualizado y contabilizada!'
            : '¡Factura de venta guardada y stock actualizado! No se contabilizó automáticamente: '.($skipReason ?? 'configura las cuentas PUC necesarias.');
        $message .= $sale->skip_dian
            ? ' Marcada como factura interna: no se envía a la DIAN.'
            : ($sentToDian
                ? ' Enviada a la DIAN a través de Factus.'
                : ' No se envió a la DIAN: '.($factusSkipReason ?? 'error desconocido.'));

        return redirect()->route('sales.index')
            ->with('success', $message)
            ->with('preview_sale_id', $sale->id);
    }

    private function nextInternalConsecutive(Company $company): string
    {
        $number = (int) $company->next_internal_sale_number;

        do {
            $consecutive = str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (Sale::query()->where('company_id', $company->id)->where('invoice_number', $consecutive)->exists());

        return $consecutive;
    }

    /**
     * Crea los renglones de la venta y aplica la salida de inventario correspondiente.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncDetailsAndStock(Sale $sale, array $items, ?string $movementReason = null): void
    {
        $movementReason ??= 'Venta '.$sale->invoice_number;

        foreach ($items as $itemData) {
            $item = Item::lockForUpdate()->findOrFail($itemData['item_id']);
            $quantity = (float) $itemData['quantity'];
            $unit = $this->inventoryUnitService->snapshot($item, isset($itemData['unit_conversion_id']) ? (int) $itemData['unit_conversion_id'] : null, 'items');
            $baseQuantity = round($quantity * $unit['unit_factor'], 4);
            if ($item->stock < $baseQuantity) {
                abort(422, "Stock insuficiente para {$item->name}.");
            }

            // CORREGIDO: operaciones matemáticas de línea subtotal, descuento y total de renglón
            $lineSubtotal = $quantity * (float) $itemData['unit_price'];
            $discount = $lineSubtotal * ((float) ($itemData['discount_percentage'] ?? 0) / 100);
            $lineTotal = $lineSubtotal - $discount;

            SaleDetail::create([
                'sale_id' => $sale->id,
                'item_id' => $item->id,
                ...$unit,
                'quantity' => $quantity,
                'unit_price' => $itemData['unit_price'],
                'iva_percentage' => $itemData['iva_percentage'],
                'discount_percentage' => $itemData['discount_percentage'] ?? 0,
                'line_total' => $lineTotal,
            ]);

            $stockBefore = (float) $item->stock;
            $item->decrement('stock', $baseQuantity);
            $item->refresh();

            InventoryMovement::create([
                'item_id' => $item->id,
                'type' => 'salida',
                'quantity' => $baseQuantity,
                'stock_before' => $stockBefore,
                'stock_after' => $item->stock,
                'reason' => $movementReason,
            ]);
        }
    }

    /**
     * Revierte el stock y la contabilización previos de una venta antes de editarla.
     */
    private function reverseDetailsAndStock(Sale $sale): void
    {
        $sale->loadMissing('details.item');

        foreach ($sale->details as $detail) {
            if ($detail->item) {
                $item = Item::lockForUpdate()->find($detail->item->id);
                if ($item) {
                    $baseQuantity = round((float) $detail->quantity * (float) $detail->unit_factor, 4);
                    $stockBefore = (float) $item->stock;
                    $item->increment('stock', $baseQuantity);
                    $item->refresh();

                    InventoryMovement::create([
                        'item_id' => $item->id,
                        'type' => 'entrada',
                        'quantity' => $baseQuantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $item->stock,
                        'reason' => 'Reversión por edición de factura '.$sale->invoice_number,
                    ]);
                }
            }
        }

        $sale->details()->delete();

        if ($sale->accounting_voucher_id) {
            $oldVoucher = $sale->accountingVoucher;
            $sale->update(['accounting_voucher_id' => null]);
            $oldVoucher?->lines()->delete();
            $oldVoucher?->delete();
        }
    }

    public function email(Request $request, Sale $sale)
    {
        $data = $request->validate(['email' => 'required|email']);
        $sale->load('details.item');
        $body = "Factura: {$sale->invoice_number}\nCliente: {$sale->customer_name}\nFecha: {$sale->sale_date}\nTotal: {$sale->total}\n\nDetalle:\n";
        foreach ($sale->details as $detail) {
            $body .= $detail->item->name.' x '.$detail->quantity.' '.($detail->unit_name ?? $detail->item->base_unit ?? 'unidad').' - '.$detail->line_total."\n";
        }
        Mail::raw($body, fn ($message) => $message->to($data['email'])->subject('Factura de venta '.$sale->invoice_number));

        return back()->with('success', 'Factura enviada por correo.');
    }

    public function show(Sale $sale)
    {
        $sale->load(['details.item', 'paymentMethod']);

        return view('sales.show', compact('sale'));
    }

    public function edit(Sale $sale)
    {
        $sale->load(['details.item', 'paymentMethod']);
        $items = Item::with('unitConversions')->where('type', 'producto')->orderBy('name')->get();
        $paymentMethods = $this->paymentMethodService->available();
        $productOptions = $items->map(fn (Item $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'price' => $item->sale_price,
            'stock' => $item->stock,
            'base_unit' => $item->base_unit ?: 'unidad',
            'units' => $item->unitConversions->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'factor' => $unit->factor,
                'sale_price' => $unit->sale_price,
            ])->values(),
        ])->values();
        $customers = ThirdParty::where('is_customer', true)->where('active', true)->orderBy('name')->get();
        $withholdings = config('colombia_withholdings');

        return view('sales.edit', compact('sale', 'items', 'paymentMethods', 'productOptions', 'customers', 'withholdings'));
    }

    public function update(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255', Rule::unique('sales', 'invoice_number')->where('company_id', session('company_id'))->ignore($sale->id)],
            'customer_name' => 'required|string|max:255',
            'customer_document' => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:255',
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')->where('company_id', session('company_id')),
            ],
            'sale_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'iva_total' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'withholding_concept' => ['required', 'string', Rule::in(array_keys(config('colombia_withholdings.sales_concepts')))],
            'retention_base' => 'required|numeric|min:0',
            'retention_total' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_conversion_id' => ['nullable', 'integer', 'exists:item_unit_conversions,id'],
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.iva_percentage' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $retention = $this->salesRetentionCalculator->calculate(
            $data['withholding_concept'],
            (float) $data['subtotal'],
            (float) $data['discount_total'],
            (float) $data['iva_total'],
        );
        $data['retention_base'] = $retention['retention_base'];
        $data['retention_total'] = $retention['retention_total'];
        $data['total'] = round((float) $data['subtotal'] - (float) $data['discount_total'] + (float) $data['iva_total'] - $retention['retention_total'], 2);

        DB::transaction(function () use ($data, $sale, $request): void {
            $this->reverseDetailsAndStock($sale);

            $sale->update([
                'invoice_number' => $data['invoice_number'],
                'customer_name' => $data['customer_name'],
                'customer_document' => $data['customer_document'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'sale_date' => $data['sale_date'],
                'subtotal' => $data['subtotal'],
                'iva_total' => $data['iva_total'],
                'discount_total' => $data['discount_total'],
                'withholding_concept' => $data['withholding_concept'],
                'retention_base' => $data['retention_base'],
                'retention_total' => $data['retention_total'],
                'total' => $data['total'],
                'skip_dian' => $request->boolean('skip_dian'),
            ]);

            $this->syncDetailsAndStock($sale, $data['items'], 'Edición de factura '.$data['invoice_number']);
        });

        $skipReason = null;
        $accountingVoucher = $this->accountingEntryService->postSale($sale->fresh(), $skipReason);

        return redirect()->route('sales.index', ['preview' => $sale->id])->with('success', $accountingVoucher
            ? '¡Factura actualizada, stock recalculado y vuelta a contabilizar correctamente!'
            : '¡Factura actualizada y stock recalculado! No se contabilizó automáticamente: '.($skipReason ?? 'configura las cuentas PUC necesarias.'));
    }

    public function statement(Sale $sale)
    {
        $documents = Sale::query()
            ->where('customer_name', $sale->customer_name)
            ->with(['payments.accountingVoucher', 'payments.paymentMethod', 'creditNotes'])
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get();

        $balance = $documents->sum(fn (Sale $document) => $document->balanceDue((float) $document->total));
        $paymentMethods = PaymentMethod::query()->with('account')->whereNotNull('chart_of_account_id')->orderBy('name')->get();
        $counterAccounts = ChartOfAccount::query()->where('active', true)->where('allows_posting', true)->orderBy('code')->get();
        $defaultCounterAccountId = $counterAccounts->firstWhere('code', '1305')?->id;

        return view('sales.statement', [
            'customer' => $sale->customer_name,
            'documents' => $documents,
            'balance' => $balance,
            'paymentMethods' => $paymentMethods,
            'counterAccounts' => $counterAccounts,
            'defaultCounterAccountId' => $defaultCounterAccountId,
        ]);
    }

    public function account(Sale $sale)
    {
        if ($sale->accounting_voucher_id) {
            return back()->with('success', 'Esta factura ya estaba contabilizada.');
        }

        $skipReason = null;
        $voucher = $this->accountingEntryService->postSale($sale, $skipReason);

        return back()->with('success', $voucher
            ? '¡Factura contabilizada correctamente!'
            : 'No se pudo contabilizar: '.($skipReason ?? 'configura las cuentas PUC necesarias.'));
    }

    public function xml(Sale $sale)
    {
        $sale->load('details.item');
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Invoice></Invoice>');
        $xml->addChild('InvoiceNumber', $sale->invoice_number);
        $xml->addChild('CustomerName', $sale->customer_name);
        $xml->addChild('Date', $sale->sale_date->toDateString());
        $xml->addChild('Total', number_format($sale->total, 2, '.', ''));
        $lines = $xml->addChild('Lines');
        foreach ($sale->details as $detail) {
            $line = $lines->addChild('Line');
            $line->addChild('Product', $detail->item->name);
            $line->addChild('Quantity', (string) $detail->quantity);
            $line->addChild('Unit', $detail->unit_name ?? $detail->item->base_unit ?? 'unidad');
            $line->addChild('UnitPrice', number_format($detail->unit_price, 2, '.', ''));
            $line->addChild('Total', number_format($detail->line_total, 2, '.', ''));
        }

        return response($xml->asXML(), 200, ['Content-Type' => 'application/xml', 'Content-Disposition' => 'attachment; filename="'.$sale->invoice_number.'.xml"']);
    }
}
