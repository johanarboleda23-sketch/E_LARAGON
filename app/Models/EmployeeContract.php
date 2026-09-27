<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class EmployeeContract extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'active' => 'boolean'];

    public function employee()
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_id');
    }
}
