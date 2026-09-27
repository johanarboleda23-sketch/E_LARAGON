<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'amount',
        'type',
        'description',
        'date',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    // Additional methods for business logic can be added here
}
