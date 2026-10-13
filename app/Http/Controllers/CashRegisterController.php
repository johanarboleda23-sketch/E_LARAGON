<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterExpense;
use App\Models\CashRegisterMovement;
use App\Models\CashRegisterSession;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
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

    public const SETTLEMENT_ACTIONS = [
        'cash'             => 'EFECTIVO',
        'bank'             => 'BANCO',
        'credit'           => 'CRÉDITO',
        'collection'       => 'COBRO DE CARTERA',
        'partial_return'   => 'DEVOLUCIÓN PARCIAL',
        'full_return'      => 'DEVOLUCIÓN TOTAL',
        'direct_exchange'  => 'CAMBIO MANO A MANO',
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

        // Cargar rutas de hoy
        $routes = class_exists(DeliveryRoute::class) 
            ? DeliveryRoute::whereDate('route_date', $today)->orderBy('id', 'desc')->get() 
            : collect();

        // Consulta de paradas sin fallas de relación
        $deliveryStopsQuery = DeliveryStop::query()->whereNotNull('sale_id');

        if ($selectedRouteId) {
            $deliveryStopsQuery->where('delivery_route_id', $selectedRouteId);
        } else {
            $todayRouteIds = $routes->pluck('id')->toArray();
            if (!empty($todayRouteIds)) {
                $deliveryStopsQuery->whereIn('delivery_route_id', $todayRouteIds);
            }
        }

        $deliveryStops = $deliveryStopsQuery->get();
        $todayExpenses = CashRegisterExpense::whereDate('expense_date', $today)->latest()->get();

        return view('cash-register.index', [
            'fundType'          => $fundType,
            'fundTypes'         => defined('App\Models\CashRegisterSession::FUND_TYPES') ? CashRegisterSession::FUND_TYPES : ['general' => 'General'],
            'openSession'       => $openSession,
            'recentSessions'    => $recentSessions,
            'routes'            => $routes,
            'selectedRouteId'   => $selectedRouteId ?? '',
            'deliveryStops'     => $deliveryStops,
            'todayExpenses'     => $todayExpenses,
            'expenseCategories' => defined('App\Models\CashRegisterExpense::CATEGORIES') ? CashRegisterExpense::CATEGORIES : [],
            'settlementActions' => self::SETTLEMENT_ACTIONS,
            'denominations'     => self::DENOMINATIONS,
        ]);
    }

    public function settleBatch(Request $request)
    {
        $data = $request->validate([
            'stops'                  => ['nullable', 'array'],
            'stops.*.payment_action' => ['required', 'string', 'in:'.implode(',', array_keys(self::SETTLEMENT_ACTIONS))],
            'stops.*.amount_paid'    => ['required', 'numeric', 'min:0'],
            'expenses'               => ['nullable', 'array'],
            'expenses.*.concept'     => ['required_with:expenses', 'string', 'max:255'],
            'expenses.*.amount'      => ['required_with:expenses', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($data) {
            if (!empty($data['stops'])) {
                foreach ($data['stops'] as $stopId => $stopData) {
                    $stop = DeliveryStop::find($stopId);
                    if ($stop) {
                        $stop->update([
                            'payment_action'   => $stopData['payment_action'],
                            'collected_amount' => $stopData['amount_paid'],
                            'status'           => 'delivered',
                        ]);

                        CashRegisterMovement::where('delivery_stop_id', $stop->id)->delete();

                        if (in_array($stopData['payment_action'], ['cash', 'bank'])) {
                            CashRegisterMovement::create([
                                'delivery_stop_id' => $stop->id,
                                'sale_id'          => $stop->sale_id,
                                'movement_type'    => $stopData['payment_action'],
                                'amount'           => $stopData['amount_paid'],
                                'description'      => 'Liquidación '.self::SETTLEMENT_ACTIONS[$stopData['payment_action']],
                                'created_by'       => auth()->id(),
                            ]);
                        }
                    }
                }
            }

            if (!empty($data['expenses'])) {
                foreach ($data['expenses'] as $expenseData) {
                    if (!empty($expenseData['concept']) && !empty($expenseData['amount'])) {
                        CashRegisterExpense::create([
                            'category'     => 'General',
                            'concept'      => $expenseData['concept'],
                            'amount'       => $expenseData['amount'],
                            'expense_date' => now()->toDateString(),
                            'created_by'   => auth()->id(),
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Liquidación y gastos procesados exitosamente.');
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'fund_type'       => ['required', 'string'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $alreadyOpen = CashRegisterSession::where('fund_type', $data['fund_type'])->where('status', 'open')->exists();
        abort_if($alreadyOpen, 422, 'Ya hay una sesión de esta caja abierta.');

        CashRegisterSession::create([
            'fund_type'       => $data['fund_type'],
            'status'          => 'open',
            'opened_at'       => now(),
            'opening_balance' => $data['opening_balance'],
            'opened_by'        => auth()->id(),
        ]);

        return redirect()->route('cash-register.index', ['fund_type' => $data['fund_type']])->with('success', 'Caja abierta correctamente.');
    }

    public function close(Request $request, CashRegisterSession $cashRegisterSession)
    {
        abort_unless($cashRegisterSession->isOpen(), 422, 'Esta sesión ya está cerrada.');

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $cashRegisterSession->closed_at = now();
        $cashRegisterSession->recalculateExpectedCash();
        $cashRegisterSession->counted_cash = $data['counted_cash'];
        $cashRegisterSession->difference = round($data['counted_cash'] - $cashRegisterSession->expected_cash, 2);
        $cashRegisterSession->notes = $data['notes'] ?? null;
        $cashRegisterSession->status = 'closed';
        $cashRegisterSession->closed_by = auth()->id();
        $cashRegisterSession->save();

        return redirect()->route('cash-register.index', ['fund_type' => $cashRegisterSession->fund_type])->with('success', 'Caja cerrada correctamente.');
    }
}