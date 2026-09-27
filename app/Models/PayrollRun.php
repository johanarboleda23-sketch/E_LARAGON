<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = ['payment_date' => 'date'];

    public function lines()
    {
        return $this->hasMany(PayrollLine::class);
    }
}
