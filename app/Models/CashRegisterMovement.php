<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterMovement extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'cash' => '1105 Efectivo',
        'bank' => '1120 Banco',
        'credit' => '1305 Crédito',
    ];

    protected $guarded = [];

    public function deliveryStop(): BelongsTo
    {
        return $this->belongsTo(DeliveryStop::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
