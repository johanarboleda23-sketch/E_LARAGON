<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AccountingVoucherLine extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function voucher()
    {
        return $this->belongsTo(AccountingVoucher::class, 'accounting_voucher_id');
    }
}
