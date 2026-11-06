<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PurchaseDetail;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CostController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2200'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $dateRange = [$start->toDateString(), $start->endOfMonth()->toDateString()];
        $items = Item::query()
            ->where('type', 'producto')
            ->orderBy('name')
            ->get();
        $monthCosts = PurchaseDetail::query()
            ->where('purchase_line_type', 'producto')
            ->whereNotNull('item_id')
            ->whereHas('purchase', fn ($query) => $query
                ->whereDate('purchase_date', '>=', $dateRange[0])
                ->whereDate('purchase_date', '<=', $dateRange[1]))
            ->selectRaw('item_id, SUM(quantity) as purchased_quantity, SUM(quantity * cost_price) as purchase_cost')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        return view('costs.index', compact('items', 'monthCosts', 'year', 'month'));
    }

    /**
     * Actualiza el margen deseado de un producto y, si se solicita, aplica el precio de venta
     * sugerido (costo promedio * (1 + margen%)) como el nuevo precio de venta vigente.
     */
    public function update(Request $request, Item $item): RedirectResponse
    {
        abort_if($item->type !== 'producto', 422, 'Solo los productos manejan costo e inventario.');

        $data = $request->validate([
            'desired_margin_percentage' => ['required', 'numeric', 'min:0', 'max:1000'],
            'apply_to_sale_price' => ['nullable', 'boolean'],
        ]);

        $item->update(['desired_margin_percentage' => $data['desired_margin_percentage']]);

        $message = 'Margen deseado actualizado.';

        if ($request->boolean('apply_to_sale_price')) {
            $suggested = $item->suggestedSalePrice();
            if ($suggested === null) {
                return back()->with('success', $message.' No se pudo calcular el precio sugerido: el producto aún no tiene costo promedio (necesita al menos una compra).');
            }
            $item->update(['sale_price' => $suggested]);
            $message .= ' Precio de venta actualizado a $'.number_format($suggested, 2).'.';
        }

        return back()->with('success', $message);
    }
}
