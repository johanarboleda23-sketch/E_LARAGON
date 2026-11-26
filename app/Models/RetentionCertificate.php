<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionCertificate extends Model
{
    use BelongsToCompany;

    public const CUSTOMER = 'customer';

    public const SUPPLIER = 'supplier';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'account_breakdown' => 'array',
            'source_voucher_ids' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
