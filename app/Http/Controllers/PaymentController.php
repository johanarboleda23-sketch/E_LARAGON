<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Registra un abono/pago sobre una venta, compra o documento soporte. El tipo de documento
     * viene fijo por ruta para no exponer la resolución polimórfica directamente al usuario.
     */
    public function storeForSale(Request $request, Sale $sale)
    {
        return $this->store($request, $sale, $sale->total, route('sales.statement', $sale));
    }

    public function storeForPurchase(Request $request, Purchase $purchase)
    {
        return $this->store($request, $purchase, $purchase->total_pagar, route('purchases.statement', $purchase));
    }

    public function storeForSupportDocument(Request $request, SupportDocument $document)
    {
        return $this->store($request, $document, $document->total, route('support-documents.statement', $document));
    }

    /**
     * @param  Sale|Purchase|SupportDocument  $document
     */
    private function store(Request $request, $document, float $total, string $redirectTo)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $balance = $document->balanceDue($total);
        if ($data['amount'] > $balance + 0.01) {
            return back()->withErrors(['amount' => 'El abono no puede superar el saldo pendiente ($'.number_format($balance, 2).').'])->withInput();
        }

        $document->payments()->create([
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'method' => $data['method'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect($redirectTo)->with('success', 'Abono registrado correctamente.');
    }
}
