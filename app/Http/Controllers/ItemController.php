<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    // Muestra la lista de productos/servicios y la opción Modal
    public function index()
    {
        $items = Item::latest()->get();
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
        $request->validate([
            'type' => 'required|in:producto,servicio',
            'name' => 'required|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
        ]);

        Item::create([
            'type' => $request->type,
            'name' => $request->name,
            'code' => $request->code,
            'sale_price' => $request->sale_price,
            'purchase_price' => $request->type === 'producto' ? $request->purchase_price : null,
            'stock' => $request->type === 'producto' ? ($request->stock ?? 0) : 0,
            'min_stock' => $request->type === 'producto' ? ($request->min_stock ?? 0) : 0,
        ]);

        return redirect()->route('items.index')->with('success', 'Registro creado con éxito.');
    }
}