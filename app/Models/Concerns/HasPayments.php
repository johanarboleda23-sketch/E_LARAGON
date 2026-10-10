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
        return (float) $this->payments()->sum('amount');
    }

    public function balanceDue(float $total): float
    {
        return round($total - $this->paidAmount(), 2);
    }
}
