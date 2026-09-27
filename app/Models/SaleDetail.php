<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
