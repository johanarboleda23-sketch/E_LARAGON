<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    use BelongsToCompany;

    protected $guarded = [];
}
