<?php

namespace App\Http\Controllers;

use App\Models\AccountingVoucher;
use App\Models\CashRegisterExpense;
use App\Models\CashRegisterSession;
use App\Models\ChartOfAccount;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\DeliveryStopReturn;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\AccountingEntryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashRegisterController extends Controller
{
    public function __construct(private readonly AccountingEntryService $accountingEntryService) {}

    private const SETTLEMENT_ACTIONS = [
        '1105' => '1105 EFECTIVO',
        '1120' => '1120 BANCO / TRANSFERENCIA',
        '1305' => '1305 CRÉDITO',
        'return_total' => 'DEVOLUCIÓN TOTAL',
        'return_partial' => 'DEVOLUCIÓN PARCIAL',
        'exchange' => 'CRUCE DE PRODUCTOS',
        'reschedule' => 'VOLVER A PROGRAMAR',
        'collection' => 'COBRO DE CARTERA',
        'remove' => 'QUITAR FACTURA DEL LOTE',
    ];

    private const DENOMINATIONS = [
        'bill_100000' => 100000,
        'bill_50000' => 50000,
        'bill_20000' => 20000,
        'bill_10000' => 10000,
        'bill_5000' => 5000,
        'bill_2000' => 2000,
        'bill_1000' => 1000,
        'coin_1000' => 1000,
        'coin_500' => 500,
        'coin_200' => 200,
        'coin_100' => 100,
        'coin_50' => 50,
    ];

    public function index(Request $request): View
    {
        $fundType = $request->query('fund_type', 'general');
        abort_unless(is_string($fundType) && array_key_exists($fundType, CashRegisterSession::FUND_TYPES), 404);

        $openSession = CashRegisterSession::query()
            ->where('fund_type', $fundType)
            ->where('status', 'open')
            ->first();
        if ($openSession) {
            $openSession->recalculateExpectedCash();
            $openSession->load('expenses');
        }

        $recentSessions = CashRegisterSession::query()
            ->where('fund_type', $fundType)
            ->where('status', 'closed')
            ->latest('closed_at')
            ->take(15)
            ->get();

        $routes = DeliveryRoute::query()->latest('route_date')->latest('id')->get();
        $selectedRouteId = $request->integer('route_id') ?: null;
        $selectedRoute = $selectedRouteId
            ? DeliveryRoute::query()->find($selectedRouteId)
            : null;
        $deliveryStops = $selectedRoute
            ? $selectedRoute->stops()->with(['sale.details.item', 'returns'])->get()
            : collect();
        $availableSales = Sale::query()
            ->whereNotIn('id', DeliveryStop::query()->whereNotNull('sale_id')->select('sale_id'))
            ->latest('sale_date')
            ->get();
        $receivableSales = Sale::query()
            ->with('payments')
            ->latest('sale_date')
            ->take(100)
            ->get()
            ->filter(fn (Sale $sale): bool => (float) $sale->total - (float) $sale->payments->sum('amount') > 0);
        $availableItems = Item::query()->where('type', 'producto')->orderBy('name')->get();

        return view('cash-register.index', [
            'fundType' => $fundType,
            'fundTypes' => CashRegisterSession::FUND_TYPES,
            'openSession' => $openSession,
            'recentSessions' => $recentSessions,
            'routes' => $routes,
            'selectedRoute' => $selectedRoute,
            'selectedRouteId' => $selectedRouteId,
            'deliveryStops' => $deliveryStops,
            'availableSales' => $availableSales,
            'receivableSales' => $receivableSales,
            'availableItems' => $availableItems,
            'settlementActions' => self::SETTLEMENT_ACTIONS,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fund_type' => ['required', 'string', 'in:'.implode(',', array_keys(CashRegisterSession::FUND_TYPES))],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $alreadyOpen = CashRegisterSession::where('fund_type', $data['fund_type'])->where('status', 'open')->exists();
        abort_if($alreadyOpen, 422, 'Ya hay una sesión de esta caja abierta. Ciérrala antes de abrir una nueva.');

        CashRegisterSession::create([
            'fund_type' => $data['fund_type'],
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => $data['opening_balance'],
            'opened_by' => auth()->id(),
        ]);

        return redirect()->route('cash-register.index', ['fund_type' => $data['fund_type']])->with('success', 'Caja abierta correctamente.');
    }

    public function close(Request $request, CashRegisterSession $cashRegisterSession): RedirectResponse
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            ...collect(self::DENOMINATIONS)->mapWithKeys(
                fn (int $value, string $field): array => [$field => ['nullable', 'integer', 'min:0', 'max:100000']]
            )->all(),
        ]);

        DB::transaction(function () use ($cashRegisterSession, $data): void {
            $cashRegisterSession = CashRegisterSession::query()->lockForUpdate()->findOrFail($cashRegisterSession->id);
            abort_unless($cashRegisterSession->isOpen(), 422, 'Esta sesión ya está cerrada.');
            $cashRegisterSession->closed_at = now();
            $cashRegisterSession->recalculateExpectedCash();

            $countedFromDenominations = 0;
            foreach (self::DENOMINATIONS as $field => $value) {
                $count = (int) ($data[$field] ?? 0);
                $cashRegisterSession->{$field} = $count;
                $countedFromDenominations += $count * $value;
            }

            $cashRegisterSession->counted_cash = $countedFromDenominations > 0
                ? $countedFromDenominations
                : (float) $data['counted_cash'];
            $cashRegisterSession->difference = round($cashRegisterSession->counted_cash - $cashRegisterSession->expected_cash, 2);
            $cashRegisterSession->notes = $data['notes'] ?? null;
            $cashRegisterSession->status = 'closed';
            $cashRegisterSession->closed_by = auth()->id();
            $cashRegisterSession->save();
        });
        $cashRegisterSession->refresh();

        $message = abs($cashRegisterSession->difference) < 0.01
            ? '¡Caja cuadrada perfectamente!'
            : ($cashRegisterSession->difference > 0
                ? 'Caja cerrada con sobrante de $'.number_format($cashRegisterSession->difference, 2).'.'
                : 'Caja cerrada con faltante de $'.number_format(abs($cashRegisterSession->difference), 2).'.');

        return redirect()->route('cash-register.index', ['fund_type' => $cashRegisterSession->fund_type])->with('success', $message);
    }

    public function settleBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'route_id' => ['required', 'integer'],
            'session_id' => ['required', 'integer'],
            'stops' => ['nullable', 'array'],
            'stops.*.payment_action' => ['required', 'in:1105,1120,1305,return_total,return_partial,exchange,reschedule,collection,remove'],
            'stops.*.amount_paid' => ['nullable', 'numeric', 'min:0'],
            'stops.*.collection_method' => ['nullable', 'in:1105,1120'],
            'stops.*.return_quantities' => ['nullable', 'array'],
            'stops.*.return_quantities.*' => ['nullable', 'integer', 'min:0'],
            'stops.*.replacement_item_id' => ['nullable', 'integer'],
            'stops.*.replacement_quantity' => ['nullable', 'integer', 'min:1'],
            'route_date' => ['nullable', 'date_format:Y-m-d'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.category' => ['required', 'in:combustible,proveedor,transferencia,otro'],
            'expenses.*.concept' => ['required', 'string', 'max:255'],
            'expenses.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $route = DeliveryRoute::query()->findOrFail($data['route_id']);
        $session = CashRegisterSession::query()->findOrFail($data['session_id']);
        abort_unless($session->isOpen(), 422, 'La sesión de caja ya está cerrada.');

        DB::transaction(function () use ($data, $route, $session): void {
            $session = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($session->isOpen(), 422, 'La sesión de caja ya está cerrada.');
            foreach ($data['stops'] ?? [] as $stopId => $stopData) {
                $stop = $route->stops()->with('sale')->lockForUpdate()->findOrFail($stopId);
                if (! $stop->sale) {
                    continue;
                }
                $sale = Sale::query()->lockForUpdate()->findOrFail($stop->sale_id);

                $action = $stopData['payment_action'];
                if ($action === 'remove') {
                    $stop->update(['sale_id' => null, 'payment_action' => null, 'collected_amount' => 0, 'payment_method' => null]);
                    continue;
                }

                if ($action === 'reschedule') {
                    abort_unless(! empty($data['route_date']), 422, 'Selecciona la nueva fecha de la ruta.');
                    $route->update(['route_date' => $data['route_date']]);
                    $stop->update(['payment_action' => $action]);
                    continue;
                }

                if (in_array($action, ['return_total', 'return_partial', 'exchange'], true)) {
                    if ($action === 'return_total') {
                        $detailRows = $stop->sale->details()->get();
                        $quantities = $detailRows->mapWithKeys(function (SaleDetail $detail) use ($stop): array {
                            $returned = (int) $stop->returns()->where('sale_detail_id', $detail->id)->sum('quantity');

                            return [$detail->id => max(0, (int) $detail->quantity - $returned)];
                        })->all();
                    } else {
                        $quantities = $stopData['return_quantities'] ?? [];
                    }
                    abort_unless(collect($quantities)->contains(fn ($quantity): bool => (int) $quantity > 0), 422, 'Selecciona al menos un producto y una cantidad.');
                    $returnedValue = $action === 'exchange'
                        ? $this->returnedValue($stop, $quantities)
                        : 0;
                    $this->restockDeliveryStop($stop, $quantities);

                    if ($action === 'exchange') {
                        $replacementItem = Item::query()->lockForUpdate()->findOrFail($stopData['replacement_item_id'] ?? 0);
                        $replacementQuantity = (int) ($stopData['replacement_quantity'] ?? 0);
                        abort_if($replacementQuantity < 1, 422, 'Indica la cantidad del producto de reemplazo.');
                        abort_if((int) $replacementItem->stock < $replacementQuantity, 422, 'No hay existencias suficientes del producto de reemplazo.');
                        $stockBefore = (int) $replacementItem->stock;
                        $replacementItem->decrement('stock', $replacementQuantity);
                        $replacementItem->refresh();
                        InventoryMovement::create([
                            'company_id' => $replacementItem->company_id,
                            'item_id' => $replacementItem->id,
                            'type' => 'salida',
                            'quantity' => $replacementQuantity,
                            'stock_before' => $stockBefore,
                            'stock_after' => $replacementItem->stock,
                            'reason' => 'Cruce de productos en factura '.$stop->sale->invoice_number,
                        ]);
                        $replacementValue = (float) $replacementItem->sale_price * $replacementQuantity;
                        $difference = round($replacementValue - $returnedValue, 2);
                        if (abs($difference) >= 0.01) {
                            $adjustmentMethod = $stopData['collection_method'] ?? null;
                            abort_unless(in_array($adjustmentMethod, ['1105', '1120'], true), 422, 'Selecciona la cuenta para ajustar la diferencia del cruce.');
                            $voucher = $this->postExchangeAdjustment($stop, $difference, $adjustmentMethod);
                            $session->movements()->create([
                                'company_id' => $session->company_id,
                                'delivery_stop_id' => $stop->id,
                                'sale_id' => $stop->sale_id,
                                'accounting_voucher_id' => $voucher->id,
                                'created_by' => auth()->id(),
                                'movement_type' => 'exchange_difference',
                                'payment_method' => $adjustmentMethod === '1105' ? 'cash' : 'bank',
                                'amount' => abs($difference),
                                'notes' => 'Diferencia por cruce de productos factura '.$stop->sale->invoice_number,
                            ]);
                        }
                    }

                    $stop->update(['payment_action' => $action, 'collected_amount' => 0]);
                    continue;
                }

                $method = $action === 'collection' ? ($stopData['collection_method'] ?? null) : $action;
                if ($action === 'collection') {
                    abort_unless(in_array($method, ['1105', '1120'], true), 422, 'Selecciona efectivo o banco para el cobro de cartera.');
                }
                $amount = $method === '1305' ? 0 : (float) ($stopData['amount_paid'] ?? 0);
                $movement = $session->movements()
                    ->where('delivery_stop_id', $stop->id)
                    ->whereIn('movement_type', ['collection', 'receivable_collection'])
                    ->first();
                $existingPayment = $movement?->payment;
                $balance = $sale->balanceDue((float) $sale->total)
                    + (float) ($existingPayment?->amount ?? 0);
                abort_if($amount > $balance + 0.01, 422, 'El recaudo supera el saldo pendiente de la factura.');
                $accountingVoucher = null;

                if (in_array($method, ['1105', '1120'], true) && $amount > 0) {
                    $paymentData = [
                        'amount' => $amount,
                        'payment_date' => now()->toDateString(),
                        'method' => $method,
                        'notes' => 'Recaudo de lote '.$route->code,
                        'created_by' => auth()->id(),
                    ];
                    if ($existingPayment) {
                        $this->removeAccountingVoucher($movement?->accounting_voucher_id);
                        $existingPayment->update($paymentData);
                    } else {
                        $existingPayment = $sale->payments()->create($paymentData);
                    }
                    $accountingVoucher = $this->postCashPayment($existingPayment, $sale, $method);
                } elseif ($existingPayment) {
                    $this->removeAccountingVoucher($movement?->accounting_voucher_id);
                    $existingPayment->delete();
                    $existingPayment = null;
                }

                $stop->update([
                    'payment_action' => $action,
                    'collected_amount' => $amount,
                    'payment_method' => $method,
                ]);

                $movementType = $action === 'collection' ? 'receivable_collection' : 'collection';
                $movementData = [
                    'company_id' => $session->company_id,
                    'sale_id' => $stop->sale_id,
                    'payment_id' => $existingPayment?->id,
                    'accounting_voucher_id' => $accountingVoucher?->id,
                    'movement_type' => $movementType,
                    'created_by' => auth()->id(),
                    'payment_method' => match ($method) {
                        '1105' => 'cash',
                        '1120' => 'bank',
                        default => 'credit',
                    },
                    'amount' => $amount,
                    'notes' => 'Factura '.$sale->invoice_number,
                ];
                if ($movement) {
                    $movement->update($movementData);
                } else {
                    $session->movements()->create([
                        ...$movementData,
                        'delivery_stop_id' => $stop->id,
                    ]);
                }
            }

            foreach ($session->expenses as $existingExpense) {
                $this->removeAccountingVoucher($existingExpense->accounting_voucher_id);
            }
            $session->expenses()->delete();
            foreach ($data['expenses'] ?? [] as $expense) {
                $expenseRecord = $session->expenses()->create([
                    'company_id' => $session->company_id,
                    'created_by' => auth()->id(),
                    ...$expense,
                ]);
                $voucher = $this->postExpense($expenseRecord);
                $expenseRecord->update(['accounting_voucher_id' => $voucher->id]);
            }

            $session->recalculateExpectedCash();
            $session->save();
        });

        return redirect()->route('cash-register.index', [
            'fund_type' => $session->fund_type,
            'route_id' => $route->id,
        ])->with('success', 'Liquidación del lote guardada.');
    }

    /**
     * @param  array<int|string, int|string>  $quantities
     */
    private function restockDeliveryStop(DeliveryStop $stop, array $quantities): void
    {
        abort_unless($stop->sale?->skip_dian, 422, 'Las devoluciones y cruces solo están habilitados para facturas internas no enviadas a la DIAN.');

        $details = SaleDetail::query()
            ->where('sale_id', $stop->sale_id)
            ->with('item')
            ->lockForUpdate()
            ->get();

        foreach ($quantities as $detailId => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity === 0) {
                continue;
            }

            $detail = $details->firstWhere('id', (int) $detailId);
            abort_unless($detail && $detail->item, 422, 'El producto no pertenece a la factura del lote.');
            $alreadyReturned = (int) $stop->returns()->where('sale_detail_id', $detail->id)->sum('quantity');
            abort_if($quantity > (int) $detail->quantity - $alreadyReturned, 422, 'La cantidad devuelta supera la cantidad pendiente.');

            $item = Item::query()->lockForUpdate()->findOrFail($detail->item_id);
            $stockBefore = (int) $item->stock;
            $item->increment('stock', $quantity);
            $item->refresh();
            DeliveryStopReturn::create([
                'company_id' => $item->company_id,
                'delivery_stop_id' => $stop->id,
                'sale_detail_id' => $detail->id,
                'created_by' => auth()->id(),
                'quantity' => $quantity,
            ]);
            InventoryMovement::create([
                'company_id' => $item->company_id,
                'item_id' => $item->id,
                'type' => 'entrada',
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $item->stock,
                'reason' => 'Devolución de lote por factura '.$stop->sale_id,
            ]);
        }
    }

    /**
     * @param  array<int|string, int|string>  $quantities
     */
    private function returnedValue(DeliveryStop $stop, array $quantities): float
    {
        $details = SaleDetail::query()
            ->where('sale_id', $stop->sale_id)
            ->get()
            ->keyBy('id');

        return round(collect($quantities)->sum(function ($quantity, $detailId) use ($details): float {
            $detail = $details->get((int) $detailId);
            if (! $detail) {
                return 0;
            }
            $unitPrice = (float) $detail->unit_price;
            $discount = (float) $detail->discount_percentage;
            $tax = (float) $detail->iva_percentage;

            return (int) $quantity * $unitPrice * (1 - $discount / 100) * (1 + $tax / 100);
        }), 2);
    }

    private function postExchangeAdjustment(DeliveryStop $stop, float $difference, string $method): AccountingVoucher
    {
        $cashAccountId = $this->postingAccountId($method);
        $incomeAccountId = $this->postingAccountId('4135');
        abort_unless($cashAccountId && $incomeAccountId, 422, 'Configura las cuentas PUC para contabilizar la diferencia del cruce.');
        $amount = abs($difference);
        $positiveDifference = $difference > 0;

        return $this->accountingEntryService->post([
            'voucher_type' => $positiveDifference ? 'recibo_caja' : 'egreso',
            'consecutive' => 'CRUCE-'.$stop->id.'-'.Str::ulid(),
            'voucher_date' => now()->toDateString(),
            'third_party' => $stop->sale?->customer_name,
            'description' => 'Diferencia de cruce de productos, factura '.$stop->sale?->invoice_number,
        ], new Collection([
            [
                'chart_of_account_id' => $positiveDifference ? $cashAccountId : $incomeAccountId,
                'detail' => $positiveDifference ? 'Diferencia recibida por cruce' : 'Diferencia devuelta por cruce',
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'chart_of_account_id' => $positiveDifference ? $incomeAccountId : $cashAccountId,
                'detail' => 'Cruce de productos factura '.$stop->sale?->invoice_number,
                'debit' => 0,
                'credit' => $amount,
            ],
        ]));
    }

    private function postCashPayment(Payment $payment, Sale $sale, string $method): AccountingVoucher
    {
        $debitAccountId = $this->postingAccountId($method);
        $receivableAccountId = $this->postingAccountId('1305');
        abort_unless($debitAccountId && $receivableAccountId, 422, 'Configura las cuentas PUC 1105/1120 y 1305 para contabilizar el recaudo.');

        return $this->accountingEntryService->post([
            'voucher_type' => 'recibo_caja',
            'consecutive' => 'ARQ-'.$payment->id.'-'.Str::ulid(),
            'voucher_date' => $payment->payment_date,
            'third_party' => $sale->customer_name,
            'description' => 'Recaudo factura '.$sale->invoice_number,
        ], new Collection([
            [
                'chart_of_account_id' => (int) $debitAccountId,
                'detail' => 'Recaudo '.$payment->method.' factura '.$sale->invoice_number,
                'debit' => (float) $payment->amount,
                'credit' => 0,
            ],
            [
                'chart_of_account_id' => (int) $receivableAccountId,
                'detail' => 'Abono a cartera factura '.$sale->invoice_number,
                'debit' => 0,
                'credit' => (float) $payment->amount,
            ],
        ]));
    }

    private function removeAccountingVoucher(?int $voucherId): void
    {
        if (! $voucherId) {
            return;
        }

        $voucher = AccountingVoucher::query()->find($voucherId);
        if ($voucher) {
            $voucher->lines()->delete();
            $voucher->delete();
        }
    }

    private function postExpense(CashRegisterExpense $expense): AccountingVoucher
    {
        [$debitCode, $creditCode, $voucherType] = match ($expense->category) {
            'combustible' => ['5135', '1105', 'egreso'],
            'proveedor' => ['2205', '1105', 'egreso'],
            'transferencia' => ['1120', '1105', 'egreso'],
            default => ['5195', '1105', 'egreso'],
        };
        $debitAccountId = $this->postingAccountId($debitCode);
        $creditAccountId = $this->postingAccountId($creditCode);
        abort_unless($debitAccountId && $creditAccountId, 422, 'Configura las cuentas PUC necesarias para contabilizar el gasto.');

        return $this->accountingEntryService->post([
            'voucher_type' => $voucherType,
            'consecutive' => 'GASTO-'.$expense->id.'-'.Str::ulid(),
            'voucher_date' => now()->toDateString(),
            'description' => 'Gasto de arqueo: '.$expense->concept,
        ], new Collection([
            [
                'chart_of_account_id' => $debitAccountId,
                'detail' => $expense->concept,
                'debit' => (float) $expense->amount,
                'credit' => 0,
            ],
            [
                'chart_of_account_id' => $creditAccountId,
                'detail' => $expense->category === 'transferencia' ? 'Traslado de caja a banco' : 'Salida de caja',
                'debit' => 0,
                'credit' => (float) $expense->amount,
            ],
        ]));
    }

    private function postingAccountId(string $code): ?int
    {
        $exact = ChartOfAccount::query()
            ->where('code', $code)
            ->where('active', true)
            ->where('allows_posting', true)
            ->value('id');
        if ($exact) {
            return (int) $exact;
        }

        $fallback = $code === '1120' ? '1110' : $code;

        return ChartOfAccount::query()
            ->where('code', 'like', $fallback.'%')
            ->where('active', true)
            ->where('allows_posting', true)
            ->orderBy('code')
            ->value('id');
    }

    public function attachInvoice(Request $request, DeliveryRoute $route): RedirectResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer'],
        ]);
        $sale = Sale::query()->findOrFail($data['sale_id']);
        abort_if(DeliveryStop::query()->where('sale_id', $sale->id)->exists(), 422, 'La factura ya está asignada a un lote.');

        $route->stops()->create([
            'sale_id' => $sale->id,
            'third_party_id' => null,
            'sequence' => (int) $route->stops()->max('sequence') + 1,
            'customer_name' => $sale->customer_name,
            'address' => 'Dirección pendiente',
            'difficulty_score' => 50,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Factura agregada al lote sin modificar el inventario.');
    }

    public function removeInvoice(DeliveryStop $stop): RedirectResponse
    {
        abort_unless($stop->sale_id, 404);
        $stop->update(['sale_id' => null, 'payment_action' => null, 'collected_amount' => 0, 'payment_method' => null]);

        return back()->with('success', 'Factura retirada del lote; la factura y el inventario no fueron modificados.');
    }

    public function returnProducts(Request $request, DeliveryStop $stop): RedirectResponse
    {
        $data = $request->validate([
            'return_type' => ['required', 'in:return_total,return_partial'],
            'items' => ['required_if:return_type,return_partial', 'array'],
            'items.*.sale_detail_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        abort_unless($stop->sale_id, 422, 'La parada no tiene una factura asociada.');
        DB::transaction(function () use ($data, $stop): void {
            $details = SaleDetail::query()->where('sale_id', $stop->sale_id)->get();
            $quantities = $data['return_type'] === 'return_total'
                ? $details->mapWithKeys(function (SaleDetail $detail) use ($stop): array {
                    $returned = (int) $stop->returns()->where('sale_detail_id', $detail->id)->sum('quantity');

                    return [$detail->id => max(0, (int) $detail->quantity - $returned)];
                })->all()
                : collect($data['items'])->mapWithKeys(fn (array $row): array => [(int) $row['sale_detail_id'] => (int) $row['quantity']])->all();
            $this->restockDeliveryStop($stop, $quantities);
        });

        return back()->with('success', 'Los productos seleccionados regresaron al inventario. La factura no fue modificada ni enviada a la DIAN.');
    }

    public function reschedule(Request $request, DeliveryRoute $route): RedirectResponse
    {
        $data = $request->validate(['route_date' => ['required', 'date_format:Y-m-d']]);
        $route->update(['route_date' => $data['route_date']]);

        return back()->with('success', 'La fecha de toda la ruta fue reprogramada.');
    }

    public function collectReceivable(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:1105,1120'],
            'session_id' => ['required', 'integer'],
        ]);
        $sale = Sale::query()->findOrFail($data['sale_id']);
        $session = CashRegisterSession::query()->findOrFail($data['session_id']);

        DB::transaction(function () use ($data, $sale, $session): void {
            $lockedSession = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->isOpen(), 422, 'La sesión de caja ya está cerrada.');
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $balance = $sale->balanceDue((float) $sale->total);
            abort_if($data['amount'] > $balance + 0.01, 422, 'El recaudo supera el saldo pendiente de la factura.');
            $payment = $sale->payments()->create([
                'amount' => $data['amount'],
                'payment_date' => now()->toDateString(),
                'method' => $data['payment_method'],
                'notes' => 'Cobro de cartera',
                'created_by' => auth()->id(),
            ]);
            $accountingVoucher = $this->postCashPayment($payment, $sale, $data['payment_method']);
            $lockedSession->movements()->create([
                'company_id' => $lockedSession->company_id,
                'sale_id' => $sale->id,
                'payment_id' => $payment->id,
                'accounting_voucher_id' => $accountingVoucher->id,
                'created_by' => auth()->id(),
                'movement_type' => 'collection',
                'payment_method' => $data['payment_method'] === '1105' ? 'cash' : 'bank',
                'amount' => $data['amount'],
                'notes' => 'Cobro de cartera '.$sale->invoice_number,
            ]);
            $lockedSession->recalculateExpectedCash();
            $lockedSession->save();
        });

        return back()->with('success', 'Cobro de cartera registrado sin afectar el inventario.');
    }

    public function storeExpense(Request $request, CashRegisterSession $cashRegisterSession): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:combustible,proveedor,transferencia,otro'],
            'concept' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($data, $cashRegisterSession): void {
            $cashRegisterSession = CashRegisterSession::query()->lockForUpdate()->findOrFail($cashRegisterSession->id);
            abort_unless($cashRegisterSession->isOpen(), 422, 'La sesión de caja ya está cerrada.');
            $expense = $cashRegisterSession->expenses()->create([
                ...$data,
                'company_id' => $cashRegisterSession->company_id,
                'created_by' => auth()->id(),
            ]);
            $voucher = $this->postExpense($expense);
            $expense->update(['accounting_voucher_id' => $voucher->id]);
            $cashRegisterSession->recalculateExpectedCash();
            $cashRegisterSession->save();
        });

        return back()->with('success', 'Gasto registrado y contabilizado.');
    }
}
