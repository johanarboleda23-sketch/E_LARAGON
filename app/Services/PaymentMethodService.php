<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodService
{
    /**
     * @return Collection<int, PaymentMethod>
     */
    public function available(): Collection
    {
        $paymentMethods = PaymentMethod::query()->with('account')->orderBy('name')->get();

        if ($paymentMethods->isEmpty()) {
            foreach (['Efectivo', 'Bancos', 'Anticipo', 'Crédito'] as $name) {
                PaymentMethod::create([
                    'name' => $name,
                    'is_editable' => true,
                    'is_cash' => $name === 'Efectivo',
                ]);
            }

            $paymentMethods = PaymentMethod::query()->with('account')->orderBy('name')->get();
        }

        return $paymentMethods;
    }
}
