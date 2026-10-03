<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactusCredential extends Model
{
    protected $guarded = [];

    protected $casts = [
        'client_secret' => 'encrypted',
        'password' => 'encrypted',
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
