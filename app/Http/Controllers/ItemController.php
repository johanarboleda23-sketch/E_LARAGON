<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    // Muestra la lista de productos/servicios y la opción Modal
    public function index()
    {
        $items = Item::query()
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(request('type'), fn ($query, $type) => $query->where('type', $type))
            ->when(request('low_stock'), fn ($query) => $query->whereColumn('stock', '<=', 'min_stock'))
            ->latest()
            ->get();

        return view('items.index', compact('items'));
    }

    // Muestra el formulario en una página completa
    public function create()
    {
        return view('items.create');
    }

    // Guarda el producto o servicio en Laragon
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:producto,servicio',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
        ]);

        Item::create($this->itemAttributes($data));

        return redirect()->route('items.index')->with('success', 'Registro creado con éxito.');
    }

    public function edit(Item $item)
    {
        return view('items.edit', compact('item'));
    }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'type' => 'required|in:producto,servicio',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
        ]);

        $item->update($this->itemAttributes($data));

        return redirect()->route('items.index')->with('success', 'Registro actualizado con éxito.');
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()->route('items.index')->with('success', 'Registro eliminado con éxito.');
    }

    public function adjustStock(Request $request, Item $item)
    {
        abort_if($item->type !== 'producto', 422, 'Los servicios no manejan existencias.');

        $data = $request->validate([
            'movement_type' => 'required|in:entrada,salida',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $quantity = (int) $data['quantity'];
        $stockBefore = $item->stock;
        $stockAfter = $data['movement_type'] === 'entrada'
            ? $stockBefore + $quantity
            : $stockBefore - $quantity;

        abort_if($stockAfter < 0, 422, 'La salida supera el stock disponible.');

        $item->update(['stock' => $stockAfter]);
        InventoryMovement::create([
            'item_id' => $item->id,
            'type' => $data['movement_type'],
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $data['reason'] ?? 'Movimiento manual',
        ]);

        return redirect()->route('items.index')->with('success', 'Movimiento aplicado correctamente.');
    }

    private function itemAttributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sale_price' => $data['sale_price'],
            'purchase_price' => $data['type'] === 'producto' ? ($data['purchase_price'] ?? null) : null,
            'stock' => $data['type'] === 'producto' ? ($data['stock'] ?? 0) : 0,
            'min_stock' => $data['type'] === 'producto' ? ($data['min_stock'] ?? 0) : 0,
        ];
    }
}
