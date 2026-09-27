<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use BelongsToCompany;

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
}
