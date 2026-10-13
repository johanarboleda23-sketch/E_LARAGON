<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterExpense;
use App\Models\CashRegisterMovement;
use App\Models\CashRegisterSession;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public const DENOMINATIONS = [
        100000 => 'Billete de $100.000',
        50000  => 'Billete de $50.000',
        20000  => 'Billete de $20.000',
        10000  => 'Billete de $10.000',
        5000   => 'Billete de $5.000',
        2000   => 'Billete de $2.000',
        1000   => 'Billete/moneda de $1.000',
        500    => 'Moneda de $500',
        200    => 'Moneda de $200',
        100    => 'Moneda de $100',
        50     => 'Moneda de $50',
    ];

    /**
     * Lista de acciones/novedades de liquidación de logística.
     */
    public const SETTLEMENT_ACTIONS = [
        'cash'             => 'Efectivo',
        'bank'             => 'Banco / Transferencia',
        'credit'           => 'Crédito',
        'collection'       => 'Cobro de Cartera',
        'partial_return'   => 'Devolución Parcial',
        'full_return'      => 'Devolución Total',
        'direct_exchange'  => 'Cambio Mano a Mano',
    ];

    public function index(Request $request)
    {
        $fundType = $request->query('fund_type', 'general');
        $selectedRouteId = $request->query('route_id');
        $today = now()->toDateString();

        $openSession = CashRegisterSession::where('fund_type', $fundType)->where('status', 'open')->first();
        if ($openSession) {
            $openSession->recalculateExpectedCash();
        }

        $recentSessions = CashRegisterSession::where('fund_type', $fundType)
            ->where('status', 'closed')
            ->latest('closed_at')
            ->take(15)
            ->get();

        // Cargar rutas de logística para la lista desplegable de lotes/rutas
        $routes = DeliveryRoute::query()
            ->whereDate('route_date', $today)
            ->orderBy('id', 'desc')
            ->get();

        // Paradas/pedidos de la ruta seleccionada o todas las paradas del día
        $deliveryStopsQuery = DeliveryStop::query()
            ->with(['sale.customer', 'route'])
            ->whereNotNull('sale_id');

        if ($selectedRouteId) {
            $deliveryStopsQuery->where('delivery_route_id', $selectedRouteId);
        } else {
            $deliveryStopsQuery->whereHas('route', fn ($q) => $q->whereDate('route_date', $today));
        }

        $deliveryStops = $deliveryStopsQuery->get();

        $manualMovements = CashRegisterMovement::query()
            ->with('sale')
            ->whereNull('delivery_stop_id')
            ->whereDate('created_at', $today)
            ->latest()
            ->get();

        $todayExpenses = CashRegisterExpense::whereDate('expense_date', $today)->latest()->get();

        return view('cash-register.index', [
            'fundType'          => $fundType,
            'fundTypes'         => CashRegisterSession::FUND_TYPES,
            'openSession'       => $openSession,
            'recentSessions'    => $recentSessions,
            'routes'            => $routes,
            'selectedRouteId'   => $selectedRouteId,
            'deliveryStops'     => $deliveryStops,
            'manualMovements'   => $manualMovements,
            'todayExpenses'     => $todayExpenses,
            'expenseCategories' => CashRegisterExpense::CATEGORIES,
            'settlementActions' => self::SETTLEMENT_ACTIONS,
            'denominations'     => self::DENOMINATIONS,
        ]);
    }

    /**
     * Procesa la liquidación individual de una parada de logística según el tipo seleccionado.
     */
    public function processStopSettlement(Request $request, DeliveryStop $stop)
    {
        $data = $request->validate([
            'payment_action' => ['required', 'string', 'in:'.implode(',', array_keys(self::SETTLEMENT_ACTIONS))],
            'amount_paid'    => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($stop, $data) {
            $sale = $stop->sale;
            $action = $data['payment_action'];
            $amount = (float) $data['amount_paid'];

            // Actualizar estado de la entrega en logística
            $stop->update([
                'payment_action' => $action,
                'collected_amount' => $amount,
                'notes' => $data['notes'] ?? $stop->notes,
                'status' => 'delivered',
            ]);

            // Eliminar movimientos previos en caja para evitar duplicaciones si se re-liquida
            CashRegisterMovement::where('delivery_stop_id', $stop->id)->delete();

            // Calcular afectación en caja/bancos según la acción
            switch ($action) {
                case 'cash':
                case 'bank':
                    CashRegisterMovement::create([
                        'delivery_stop_id' => $stop->id,
                        'sale_id'          => $sale?->id,
                        'movement_type'    => $action,
                        'amount'           => $amount,
                        'is_collection'     => false,
                        'description'      => 'Liquidación '.self::SETTLEMENT_ACTIONS[$action].' - Parada #'.$stop->id,
                        'created_by'       => auth()->id(),
                    ]);
                    break;

                case 'collection':
                    // Cobro de cartera previa
                    if ($sale) {
                        $sale->payments()->create([
                            'amount'       => $amount,
                            'payment_date' => now()->toDateString(),
                            'method'       => 'Cobro de Cartera (Ruta)',
                            'created_by'   => auth()->id(),
                        ]);
                    }
                    CashRegisterMovement::create([
                        'delivery_stop_id' => $stop->id,
                        'sale_id'          => $sale?->id,
                        'movement_type'    => 'cash',
                        'amount'           => $amount,
                        'is_collection'     => true,
                        'description'      => 'Cobro de Cartera - Parada #'.$stop->id,
                        'created_by'       => auth()->id(),
                    ]);
                    break;

                case 'credit':
                    // No ingresa dinero en efectivo/banco a caja; la venta queda cargada a cuentas por cobrar.
                    break;

                case 'full_return':
                    // Devolución total: se anula/cancela la recepción de efectivo
                    if ($sale) {
                        $sale->update(['status' => 'cancelled']);
                    }
                    break;

                case 'partial_return':
                    // Se registra el monto devuelto en notas de la parada y el valor real cobrado
                    break;

                case 'direct_exchange':
                    // Cambio mano a mano: la mercancía sale/entra en equivalente, valor neutro en caja
                    break;
            }
        });

        return back()->with('success', 'Liquidación de la parada #'.$stop->id.' registrada correctamente.');
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'fund_type'       => ['required', 'string', 'in:'.implode(',', array_keys(CashRegisterSession::FUND_TYPES))],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $alreadyOpen = CashRegisterSession::where('fund_type', $data['fund_type'])->where('status', 'open')->exists();
        abort_if($alreadyOpen, 422, 'Ya hay una sesión de esta caja abierta. Ciérrala antes de abrir una nueva.');

        CashRegisterSession::create([
            'fund_type'       => $data['fund_type'],
            'status'          => 'open',
            'opened_at'       => now(),
            'opening_balance' => $data['opening_balance'],
            'opened_by'        => auth()->id(),
        ]);

        return redirect()->route('cash-register.index', ['fund_type' => $data['fund_type']])->with('success', 'Caja abierta correctamente.');
    }

    public function updateBase(Request $request, CashRegisterSession $cashRegisterSession)
    {
        abort_unless($cashRegisterSession->isOpen(), 422, 'Esta sesión ya está cerrada.');

        $data = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $cashRegisterSession->update(['opening_balance' => $data['opening_balance']]);

        return back()->with('success', 'Base del día actualizada.');
    }

    public function close(Request $request, CashRegisterSession $cashRegisterSession)
    {
        abort_unless($cashRegisterSession->isOpen(), 422, 'Esta sesión ya está cerrada.');

        $data = $request->validate([
            'counted_cash'    => ['nullable', 'numeric', 'min:0'],
            'denominations'   => ['nullable', 'array'],
            'denominations.*' => ['nullable', 'integer', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $denominations = collect($data['denominations'] ?? [])
            ->filter(fn ($quantity, $value) => array_key_exists((int) $value, self::DENOMINATIONS))
            ->mapWithKeys(fn ($quantity, $value) => [(int) $value => (int) $quantity])
            ->all();

        $countedCash = $denominations !== []
            ? collect($denominations)->sum(fn ($quantity, $value) => $quantity * (int) $value)
            : (float) ($data['counted_cash'] ?? 0);

        abort_if($denominations === [] && ! $request->filled('counted_cash'), 422, 'Indica el efectivo contado o su desglose por denominaciones.');

        $cashRegisterSession->closed_at = now();
        $cashRegisterSession->recalculateExpectedCash();
        $cashRegisterSession->counted_cash = $countedCash;
        $cashRegisterSession->denominations = $denominations !== [] ? $denominations : null;
        $cashRegisterSession->difference = round($countedCash - $cashRegisterSession->expected_cash, 2);
        $cashRegisterSession->notes = $data['notes'] ?? null;
        $cashRegisterSession->status = 'closed';
        $cashRegisterSession->closed_by = auth()->id();
        $cashRegisterSession->save();

        $message = sprintf(
            '%s Total efectivo: $%s. Total gastos: $%s. Diferencia: %s$%s.',
            abs($cashRegisterSession->difference) < 0.01 ? '¡Caja cuadrada perfectamente!' : 'Caja cerrada con descuadre.',
            number_format($cashRegisterSession->cash_in + $cashRegisterSession->movements_cash, 2),
            number_format($cashRegisterSession->expenses_total, 2),
            $cashRegisterSession->difference >= 0 ? '+' : '-',
            number_format(abs($cashRegisterSession->difference), 2)
        );

        return redirect()->route('cash-register.index', ['fund_type' => $cashRegisterSession->fund_type])->with('success', $message);
    }

    public function addExpense(Request $request)
    {
        $data = $request->validate([
            'category'     => ['required', 'string', 'in:'.implode(',', array_keys(CashRegisterExpense::CATEGORIES))],
            'concept'      => ['required', 'string', 'max:255'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['nullable', 'date'],
        ]);

        CashRegisterExpense::create([
            'category'     => $data['category'],
            'concept'      => $data['concept'],
            'amount'       => $data['amount'],
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
            'created_by'   => auth()->id(),
        ]);

        return back()->with('success', 'Gasto registrado: se descontará del efectivo esperado.');
    }
}