<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentMethod extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'is_editable', 'chart_of_account_id', 'bank_name', 'account_number', 'is_cash'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function bankJournal(): HasOne
    {
        return $this->hasOne(BankJournal::class);
    }
}
