<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterExpense;
use App\Models\CashRegisterMovement;
use App\Models\CashRegisterSession;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use Illuminate\Http\Request;

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

        // Obtener IDs de las rutas de hoy
        $todayRouteIds = DeliveryRoute::whereDate('route_date', $today)->pluck('id');

        // Cargar listado de rutas para el desplegable
        $routes = DeliveryRoute::whereIn('id', $todayRouteIds)->orderBy('id', 'desc')->get();

        // Filtrar paradas de forma directa sin dependencias fallidas de relaciones
        $deliveryStopsQuery = DeliveryStop::query()
            ->with(['sale.customer'])
            ->whereNotNull('sale_id');

        if ($selectedRouteId) {
            $deliveryStopsQuery->where('delivery_route_id', $selectedRouteId);
        } else {
            $deliveryStopsQuery->whereIn('delivery_route_id', $todayRouteIds);
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
            'fundTypes'         => CashRegisterSession::FUND_TYPES ?? [],
            'openSession'       => $openSession,
            'recentSessions'    => $recentSessions,
            'routes'            => $routes,
            'selectedRouteId'   => $selectedRouteId,
            'deliveryStops'     => $deliveryStops,
            'manualMovements'   => $manualMovements,
            'todayExpenses'     => $todayExpenses,
            'expenseCategories' => CashRegisterExpense::CATEGORIES ?? [],
            'paymentActions'    => DeliveryStop::PAYMENT_ACTIONS ?? [],
            'settlementActions' => self::SETTLEMENT_ACTIONS,
            'denominations'     => self::DENOMINATIONS,
        ]);
    }