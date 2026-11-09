<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThirdParty extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = ['is_customer' => 'boolean', 'is_supplier' => 'boolean', 'is_employee' => 'boolean', 'active' => 'boolean'];

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
