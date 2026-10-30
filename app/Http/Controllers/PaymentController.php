<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Registra un abono/pago sobre una venta, compra o documento soporte. El tipo de documento
     * viene fijo por ruta para no exponer la resolución polimórfica directamente al usuario.
     */
    public function storeForSale(Request $request, Sale $sale): RedirectResponse
    {
        return $this->store($request, $sale, 'total', route('sales.statement', $sale));
    }

    public function storeForPurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        return $this->store($request, $purchase, 'total_pagar', route('purchases.statement', $purchase));
    }

    public function storeForSupportDocument(Request $request, SupportDocument $document): RedirectResponse
    {
        return $this->store($request, $document, 'total', route('support-documents.statement', $document));
    }

    private function store(Request $request, Sale|Purchase|SupportDocument $document, string $totalAttribute, string $redirectTo): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($data, $document, $totalAttribute, $redirectTo): RedirectResponse {
            $lockedDocument = $document->newQuery()
                ->lockForUpdate()
                ->findOrFail($document->getKey());
            $total = (float) $lockedDocument->getAttribute($totalAttribute);
            $balance = $lockedDocument->balanceDue($total);
            $amount = round((float) $data['amount'], 2);
            if ($amount > round($balance, 2)) {
                return back()->withErrors([
                    'amount' => 'El abono no puede superar el saldo pendiente ($'.number_format($balance, 2).').',
                ])->withInput();
            }

            $lockedDocument->payments()->create([
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            return redirect($redirectTo)->with('success', 'Abono registrado correctamente.');
        });
    }
}
