<?php

namespace App\Http\Controllers;

use App\Models\AccountingVoucherLine;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Http\Request;

class FinancialDashboardController extends Controller
{
    public function index(Request $request)
    {
        $filters = validator([
            'from' => $request->query('from', now()->startOfMonth()->toDateString()),
            'to' => $request->query('to', now()->toDateString()),
        ], [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->validate();
        $from = $filters['from'];
        $to = $filters['to'];

        $totalSales = (float) Sale::whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)->sum('subtotal');
        $totalSalesWithTax = (float) Sale::whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)->sum('total');
        $totalPurchases = (float) Purchase::whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)->sum('subtotal');

        // Costo de ventas estimado: usa el costo promedio ACTUAL de cada producto vendido en el
        // periodo (no se guarda el costo historico por venta, así que es una aproximación útil
        // para ver la tendencia, no una cifra contable exacta).
        $costOfGoodsSold = (float) Sale::whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)
            ->with('details.item')
            ->get()
            ->flatMap(fn (Sale $sale) => $sale->details)
            ->sum(fn ($detail) => (float) $detail->quantity * (float) ($detail->item?->average_cost ?? 0));

        $grossProfit = round($totalSales - $costOfGoodsSold, 2);
        $grossMargin = $totalSales > 0 ? round($grossProfit / $totalSales * 100, 2) : null;

        // Gastos operativos: movimientos a cuentas clase 5 (gastos) dentro del periodo.
        $operatingExpenses = (float) AccountingVoucherLine::whereHas('voucher', fn ($query) => $query->whereDate('voucher_date', '>=', $from)->whereDate('voucher_date', '<=', $to))
            ->whereHas('account', fn ($query) => $query->where('class', 5))
            ->sum('debit');

        $netProfit = round($grossProfit - $operatingExpenses, 2);
        $netMargin = $totalSales > 0 ? round($netProfit / $totalSales * 100, 2) : null;

        // Cartera: saldo pendiente REAL (total facturado menos abonos registrados), sin
        // importar el periodo filtrado, porque es un saldo a hoy (como un balance), no un
        // movimiento del periodo.
        $receivables = (float) Sale::with('payments')->get()->sum(fn (Sale $sale) => $sale->balanceDue((float) $sale->total));
        $payables = (float) Purchase::with('payments')->get()->sum(fn (Purchase $purchase) => $purchase->balanceDue((float) $purchase->total_pagar));

        // Inventario valorizado al costo promedio actual.
        $inventoryValue = (float) Item::where('type', 'producto')->get()
            ->sum(fn (Item $item) => (float) $item->stock * (float) ($item->average_cost ?? $item->purchase_price ?? 0));

        $averageInventoryValue = $inventoryValue > 0 ? $inventoryValue : null;
        $inventoryTurnover = ($averageInventoryValue && $costOfGoodsSold > 0)
            ? round($costOfGoodsSold / $averageInventoryValue, 2)
            : null;

        // Tendencia mensual (últimos 6 meses) de ventas vs compras, para graficar barras simples.
        $monthlyTrend = collect(range(5, 0))->map(function (int $monthsAgo) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            $monthEnd = now()->subMonths($monthsAgo)->endOfMonth();

            return [
                'label' => $monthStart->translatedFormat('M Y'),
                'sales' => (float) Sale::whereDate('sale_date', '>=', $monthStart->toDateString())->whereDate('sale_date', '<=', $monthEnd->toDateString())->sum('total'),
                'purchases' => (float) Purchase::whereDate('purchase_date', '>=', $monthStart->toDateString())->whereDate('purchase_date', '<=', $monthEnd->toDateString())->sum('total_pagar'),
            ];
        });
        $maxTrendValue = max($monthlyTrend->flatMap(fn ($row) => [$row['sales'], $row['purchases']])->max(), 1);

        return view('financial-dashboard.index', [
            'from' => $from,
            'to' => $to,
            'totalSales' => $totalSalesWithTax,
            'totalPurchases' => $totalPurchases,
            'grossProfit' => $grossProfit,
            'grossMargin' => $grossMargin,
            'operatingExpenses' => $operatingExpenses,
            'netProfit' => $netProfit,
            'netMargin' => $netMargin,
            'receivables' => $receivables,
            'payables' => $payables,
            'inventoryValue' => $inventoryValue,
            'inventoryTurnover' => $inventoryTurnover,
            'monthlyTrend' => $monthlyTrend,
            'maxTrendValue' => $maxTrendValue,
        ]);
    }
}
