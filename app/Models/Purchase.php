<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPayments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use BelongsToCompany;
    use HasPayments;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['purchase_date' => 'date'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function adjustmentNotes(): HasMany
    {
        return $this->hasMany(CommercialDocument::class, 'reference_purchase_id')
            ->whereIn('document_type', ['supplier_credit_note', 'supplier_debit_note']);
    }

    public function balanceDue(float $total): float
    {
        $adjustments = $this->relationLoaded('adjustmentNotes')
            ? (float) $this->adjustmentNotes->sum('total')
            : (float) $this->adjustmentNotes()->sum('total');

        return round($total - $this->paidAmount() - $adjustments, 2);
    }

    public function accountingVoucher(): BelongsTo
    {
        return $this->belongsTo(AccountingVoucher::class);
    }
}
