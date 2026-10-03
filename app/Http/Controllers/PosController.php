<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\NumberingResolution;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\AccountingEntryService;
use App\Services\FactusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function __construct(
        private readonly AccountingEntryService $accountingEntryService,
        private readonly FactusService $factusService,
    ) {}

    public function index()
    {
        $items = Item::where('type', 'producto')->where('stock', '>', 0)->orderBy('name')->get();
        $sales = Sale::where('invoice_number', 'like', 'POS-%')->with('details.item')->latest()->take(15)->get();

        $productOptions = $items->map(fn (Item $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->sale_price,
            'stock' => $item->stock,
        ])->values();

        return view('pos.index', compact('productOptions', 'sales'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $ivaRate = 19.0;

        try {
            $invoiceNumber = NumberingResolution::allocateNext('sale') ?? 'POS-'.now()->format('YmdHis').'-'.random_int(100, 999);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        [$sale, $subtotal, $ivaTotal, $total] = DB::transaction(function () use ($data, $invoiceNumber, $ivaRate) {
            $subtotal = 0.0;
            $lines = [];

            foreach ($data['items'] as $itemData) {
                $item = Item::lockForUpdate()->findOrFail($itemData['item_id']);
                $quantity = (int) $itemData['quantity'];
                abort_if($item->stock < $quantity, 422, "Stock insuficiente para {$item->name}.");
                $lineSubtotal = $quantity * (float) $item->sale_price;
                $subtotal += $lineSubtotal;
                $lines[] = ['item' => $item, 'quantity' => $quantity, 'unit_price' => $item->sale_price, 'line_total' => $lineSubtotal];
            }

            $ivaTotal = round($subtotal * ($ivaRate / 100), 2);
            $total = round($subtotal + $ivaTotal, 2);

            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_name' => $data['customer_name'] ?? 'Consumidor final',
                'sale_date' => now()->toDateString(),
                'subtotal' => $subtotal,
                'iva_total' => $ivaTotal,
                'discount_total' => 0,
                'withholding_concept' => 'none',
                'retention_base' => 0,
                'retention_total' => 0,
                'total' => $total,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'item_id' => $line['item']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'iva_percentage' => $ivaRate,
                    'discount_percentage' => 0,
                    'line_total' => $line['line_total'],
                ]);
                $stockBefore = $line['item']->stock;
                $line['item']->decrement('stock', $line['quantity']);
                $line['item']->refresh();
                InventoryMovement::create([
                    'item_id' => $line['item']->id,
                    'type' => 'salida',
                    'quantity' => $line['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $line['item']->stock,
                    'reason' => 'Venta POS '.$sale->invoice_number,
                ]);
            }

            return [$sale, $subtotal, $ivaTotal, $total];
        });

        $skipReason = null;
        $accountingVoucher = $this->accountingEntryService->postSale($sale, $skipReason);

        $factusSkipReason = null;
        $sentToDian = $this->factusService->sendSaleInvoice($sale, session('company_id'), $factusSkipReason);

        $message = $accountingVoucher
            ? "¡Venta POS {$sale->invoice_number} registrada y contabilizada!"
            : "Venta POS {$sale->invoice_number} registrada. No se contabilizó automáticamente: ".($skipReason ?? 'configura las cuentas PUC necesarias.');
        $message .= $sentToDian
            ? ' Enviada a la DIAN a través de Factus.'
            : ' No se envió a la DIAN: '.($factusSkipReason ?? 'revisa la configuración de Factus.');

        return back()->with('success', $message);
    }
}
