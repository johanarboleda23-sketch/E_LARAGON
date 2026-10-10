<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPayments;
use Illuminate\Database\Eloquent\Model;

class SupportDocument extends Model
{
    use BelongsToCompany;
    use HasPayments;

    protected $guarded = [];

    protected $casts = ['document_date' => 'date'];

    public function supplier()
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accountingVoucher()
    {
        return $this->belongsTo(AccountingVoucher::class);
    }
}
