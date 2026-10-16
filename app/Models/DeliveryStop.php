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

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
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

    public function returns(): HasMany
    {
        return $this->hasMany(DeliveryStopReturn::class);
    }
}
