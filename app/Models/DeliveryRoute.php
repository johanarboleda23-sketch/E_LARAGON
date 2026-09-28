<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRoute extends Model
{
    use BelongsToCompany;

    public const STATUSES = [
        'draft' => 'Borrador',
        'planned' => 'Planificada',
        'in_progress' => 'En reparto',
        'completed' => 'Completada',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'route_date' => 'date',
        ];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('sequence');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
