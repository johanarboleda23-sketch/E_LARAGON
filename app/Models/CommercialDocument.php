<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\CommercialDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommercialDocument extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<CommercialDocumentFactory> */
    use HasFactory;

    public const TYPES = [
        'quotation' => 'Cotización',
        'sales_order' => 'Orden de venta',
        'remission' => 'Remisión',
        'purchase_order' => 'Orden de compra',
        'customer_credit_note' => 'Nota crédito clientes',
        'supplier_debit_note' => 'Nota débito proveedores',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'due_date' => 'date',
            'converted_at' => 'datetime',
            'accounted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommercialDocumentLine::class);
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function convertedSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }

    public function convertedPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'converted_purchase_id');
    }

    public function referenceSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'reference_sale_id');
    }

    public function referencePurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'reference_purchase_id');
    }

    public function accountingVoucher(): HasOne
    {
        return $this->hasOne(AccountingVoucher::class);
    }
}
