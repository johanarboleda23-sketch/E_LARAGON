<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SupportDocument extends Model
{
    use BelongsToCompany;

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
}
