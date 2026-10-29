<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterExpense extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = [
        'expense_date' => 'date',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }
}
