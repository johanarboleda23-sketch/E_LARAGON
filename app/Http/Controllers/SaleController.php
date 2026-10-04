<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\NumberingResolution;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\ThirdParty;
use App\Services\AccountingEntryService;
use App\Services\FactusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SaleController extends Controller
{
    public function __construct(
        private readonly AccountingEntryService $accountingEntryService,
        private readonly FactusService $factusService,
    ) {}

    public function index()
    {
        $items = Item::where('type', 'producto')->orderBy('name')->get();
        $sales = Sale::with(['details.item', 'creator'])->latest()->take(20)->get();
        $customers = ThirdParty::where('is_customer', true)->where('active', true)->orderBy('name')->get();
        $withholdings = config('colombia_withholdings');
        $nextConsecutive = NumberingResolution::peekNext('sale');
        $productOptions = $items->map(function (Item $item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->sale_price,
                'stock' => $item->stock,
            ];
        })->values();

        return view('sales.index', compact('items', 'sales', 'productOptions', 'withholdings', 'customers', 'nextConsecutive'));
    }

    public function store(Request $request)
    {
        $hasActiveResolution = NumberingResolution::query()->where('document_type', 'sale')->where('active', true)->exists();

        $data = $request->validate([
            'invoice_number' => [$hasActiveResolution ? 'nullable' : 'required', 'string', 'max:255', 'unique:sales,invoice_number'],
            'customer_name' => 'required|string|max:255',
            'customer_document' => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:255',
            'sale_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'iva_total' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'withholding_concept' => 'required|string|in:'.implode(',', array_keys(config('colombia_withholdings.concepts'))),
            'retention_base' => 'required|numeric|min:0',
            'retention_total' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.iva_percentage' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $concept = config('colombia_withholdings.concepts.'.$data['withholding_concept']);
        $retentionBase = $concept['base_on'] === 'iva' ? (float) $data['iva_total'] : (float) $data['subtotal'] - (float) $data['discount_total'];
        $minimumBase = (float) $concept['base_uvt'] * (float) config('colombia_withholdings.uvt');
        $retentionBase = $retentionBase >= $minimumBase ? $retentionBase : 0;
        $retentionTotal = round($retentionBase * (float) $concept['rate'], 2);
        $data['retention_base'] = $retentionBase;
        $data['retention_total'] = $retentionTotal;
        $data['total'] = round((float) $data['subtotal'] - (float) $data['discount_total'] + (float) $data['iva_total'] - $retentionTotal, 2);

        try {
            $invoiceNumber = NumberingResolution::allocateNext('sale') ?? $data['invoice_number'];
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice_number' => $e->getMessage()])->withInput();
        }

        $sale = DB::transaction(function () use ($data, $invoiceNumber) {
            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_name' => $data['customer_name'],
                'customer_document' => $data['customer_document'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'sale_date' => $data['sale_date'],
                'subtotal' => $data['subtotal'],
                'iva_total' => $data['iva_total'],
                'discount_total' => $data['discount_total'],
                'withholding_concept' => $data['withholding_concept'],
                'retention_base' => $data['retention_base'],
                'retention_total' => $data['retention_total'],
                'total' => $data['total'],
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $itemData) {
                $item = Item::lockForUpdate()->findOrFail($itemData['item_id']);
                $quantity = (int) $itemData['quantity'];
                if ($item->stock < $quantity) {
                    abort(422, "Stock insuficiente para {$item->name}.");
                }
                $lineSubtotal = $quantity * (float) $itemData['unit_price'];
                $discount = $lineSubtotal * ((float) ($itemData['discount_percentage'] ?? 0) / 100);
                $lineTotal = $lineSubtotal - $discount;
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_price' => $itemData['unit_price'],
                    'iva_percentage' => $itemData['iva_percentage'],
                    'discount_percentage' => $itemData['discount_percentage'] ?? 0,
                    'line_total' => $lineTotal,
                ]);
                $stockBefore = $item->stock;
                $item->decrement('stock', $quantity);
                $item->refresh();
                InventoryMovement::create([
                    'item_id' => $item->id,
                    'type' => 'salida',
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $item->stock,
                    'reason' => 'Venta '.$sale->invoice_number,
                ]);
            }

            return $sale;
        });

        $skipReason = null;
        $accountingVoucher = $this->accountingEntryService->postSale($sale, $skipReason);

        $factusSkipReason = null;
        $sentToDian = $this->factusService->sendSaleInvoice($sale, session('company_id'), $factusSkipReason);

        $message = $accountingVoucher
            ? '¡Factura de venta guardada, stock actualizado y contabilizada!'
            : '¡Factura de venta guardada y stock actualizado! No se contabilizó automáticamente: '.($skipReason ?? 'configura las cuentas PUC necesarias.');
        $message .= $sentToDian
            ? ' Enviada a la DIAN a través de Factus.'
            : ' No se envió a la DIAN: '.($factusSkipReason ?? 'error desconocido.');

        return redirect()->route('sales.index')->with('success', $message);
    }

    public function email(Request $request, Sale $sale)
    {
        $data = $request->validate(['email' => 'required|email']);
        $sale->load('details.item');
        $body = "Factura: {$sale->invoice_number}\nCliente: {$sale->customer_name}\nFecha: {$sale->sale_date}\nTotal: {$sale->total}\n\nDetalle:\n";
        foreach ($sale->details as $detail) {
            $body .= $detail->item->name.' x '.$detail->quantity.' - '.$detail->line_total."\n";
        }
        Mail::raw($body, fn ($message) => $message->to($data['email'])->subject('Factura de venta '.$sale->invoice_number));

        return back()->with('success', 'Factura enviada por correo.');
    }

    public function show(Sale $sale)
    {
        $sale->load('details.item');

        return view('sales.show', compact('sale'));
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
            $line->addChild('UnitPrice', number_format($detail->unit_price, 2, '.', ''));
            $line->addChild('Total', number_format($detail->line_total, 2, '.', ''));
        }

        return response($xml->asXML(), 200, ['Content-Type' => 'application/xml', 'Content-Disposition' => 'attachment; filename="'.$sale->invoice_number.'.xml"']);
    }
}
