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

        // Cargar rutas del día
        $routes = DeliveryRoute::query()
            ->whereDate('route_date', $today)
            ->orderBy('id', 'desc')
            ->get();

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
            'paymentActions'    => DeliveryStop::PAYMENT_ACTIONS,
            'settlementActions' => self::SETTLEMENT_ACTIONS,
            'denominations'     => self::DENOMINATIONS,
        ]);
    }