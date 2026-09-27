<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'is_editable'];
}
