<?php

namespace App\Models\Concerns;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Agrega el registro de abonos/pagos a un documento (venta o compra) para calcular su saldo
 * pendiente real, en vez de asumir que todo lo facturado sigue sin pagar.
 */
trait HasPayments
{
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function paidAmount(): float
    {
        return $this->relationLoaded('payments')
            ? (float) $this->payments->sum(fn (Payment $payment): float => (float) $payment->amount + (float) $payment->withholding_total)
            : (float) $this->payments()
                ->selectRaw('COALESCE(SUM(amount + withholding_total), 0) as total')
                ->value('total');
    }

    public function balanceDue(float $total): float
    {
        return round($total - $this->paidAmount(), 2);
    }
}
