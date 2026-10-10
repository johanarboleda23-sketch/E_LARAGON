<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class CostController extends Controller
{
    public function index()
    {
        $items = Item::query()
            ->where('type', 'producto')
            ->orderBy('name')
            ->get();

        return view('costs.index', compact('items'));
    }

    /**
     * Actualiza el margen deseado de un producto y, si se solicita, aplica el precio de venta
     * sugerido (costo promedio * (1 + margen%)) como el nuevo precio de venta vigente.
     */
    public function update(Request $request, Item $item)
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
