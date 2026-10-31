<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ItemUnitConversionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUnitConversion extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ItemUnitConversionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'factor' => 'float',
            'sale_price' => 'float',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
