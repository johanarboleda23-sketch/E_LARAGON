<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryStop extends Model
{
    public const STATUSES = [
        'pending' => 'Pendiente',
        'delivered' => 'Entregada',
        'failed' => 'Fallida',
    ];

    public const PAYMENT_ACTIONS = [
        'cash' => '1105 Efectivo',
        'bank' => '1120 Banco',
        'credit' => '1305 Crédito',
        'full_return' => 'Devolución total',
        'partial_return' => 'Devolución parcial',
        'product_exchange' => 'Cruce de productos',
        'reschedule' => 'Volver a programar',
        'credit_collection' => 'Cobro de cartera',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'payment_recorded_at' => 'datetime',
            'reschedule_date' => 'date',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashRegisterMovement::class);
    }
}
