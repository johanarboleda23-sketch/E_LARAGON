<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
  // 1. Muestra la pantalla principal de compras
    public function index()
    {
        return view('purchases.index');
    }
    // 2. Guarda la factura de compra y alimenta automáticamente el inventario
    public function store(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'cost_price' => 'required|numeric|min:0',
            'iva_percentage' => 'required|numeric',
            'utility_percentage' => 'required|numeric',
        ]);

        // Iniciamos una transacción segura en la base de datos
        DB::transaction(function () use ($request) {
            $subtotal = $request->cost_price * $request->quantity;
            $iva_total = $subtotal * ($request->iva_percentage / 100);

            // Regla de Retefuente en Colombia para Compras Generales (Base 2026 aprx $1.272.000)
            $retefuente = 0;
            if ($subtotal >= 1272000) {
                $retefuente = $subtotal * 0.025; // 2.5% para Declarantes
            }

            $total_pagar = ($subtotal + $iva_total) - $retefuente;

            // A. Guardamos la Factura General
            $purchase = Purchase::create([
                'invoice_number' => $request->invoice_number,
                'provider' => $request->provider,
                'purchase_date' => now(),
                'subtotal' => $subtotal,
                'iva_total' => $iva_total,
                'retefuente' => $retefuente,
                'total_pagar' => $total_pagar,
            ]);

            // B. Buscamos el producto físico o servicio en el Inventario
            $item = Item::find($request->item_id);

            // Cálculo automático del precio de venta basado en el costo y el margen de ganancia
            $nuevo_precio_venta = $request->cost_price * (1 + ($request->utility_percentage / 100));

            // C. Guardamos el Detalle de la factura
            PurchaseDetail::create([
                'purchase_id' => $purchase->id,
                'item_id' => $item->id,
                'quantity' => $request->quantity,
                'cost_price' => $request->cost_price,
                'iva_percentage' => $request->iva_percentage,
                'iva_value' => $request->cost_price * ($request->iva_percentage / 100),
                'utility_percentage' => $request->utility_percentage,
                'calculated_sale_price' => $nuevo_precio_venta,
            ]);

            // D. ¡ALIMENTACIÓN AUTOMÁTICA DEL INVENTARIO!
            // Si es un producto, le sumamos las cantidades compradas al stock y actualizamos su precio de venta
            if ($item->type === 'producto') {
                $item->stock += $request->quantity;
                $item->purchase_price = $request->cost_price;
            }
            
            // Tanto a productos como a servicios les actualizamos el precio de venta sugerido de forma automática
            $item->sale_price = $nuevo_precio_venta;
            $item->save();
        });

        return redirect()->route('purchases.index')->with('success', '¡Factura procesada con éxito! El inventario y el precio de venta se actualizaron automáticamente.');
    }
}