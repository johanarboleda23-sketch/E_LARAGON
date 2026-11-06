<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPayments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CommercialDocument::class, 'reference_sale_id')
            ->where('document_type', 'customer_credit_note');
    }

    public function balanceDue(float $total): float
    {
        $creditedAmount = $this->relationLoaded('creditNotes')
            ? (float) $this->creditNotes->sum('total')
            : (float) $this->creditNotes()->sum('total');

        return max(0, round($total - $this->paidAmount() - $creditedAmount, 2));
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
