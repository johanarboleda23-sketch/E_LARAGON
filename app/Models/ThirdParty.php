<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ThirdParty extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = ['is_customer' => 'boolean', 'is_supplier' => 'boolean', 'is_employee' => 'boolean', 'active' => 'boolean'];

    public function contracts()
    {
        return $this->hasMany(EmployeeContract::class);
    }
}
