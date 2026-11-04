<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPayments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceInvoice extends Model
{
    use BelongsToCompany;
    use HasPayments;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'opening_balance' => 'float',
        ];
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function accountingVoucher(): BelongsTo
    {
        return $this->belongsTo(AccountingVoucher::class);
    }

    public function accountingVoucherLine(): BelongsTo
    {
        return $this->belongsTo(AccountingVoucherLine::class);
    }
}
