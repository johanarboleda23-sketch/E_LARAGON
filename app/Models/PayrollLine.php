<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PayrollLine extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_id');
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
