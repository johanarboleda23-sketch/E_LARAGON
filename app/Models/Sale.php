<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPayments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use BelongsToCompany;
    use HasPayments;

    protected $guarded = [];

    protected $casts = ['sale_date' => 'date'];

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accountingVoucher()
    {
        return $this->belongsTo(AccountingVoucher::class);
    }

    public function deliveryStop(): HasOne
    {
        return $this->hasOne(DeliveryStop::class);
    }
}
