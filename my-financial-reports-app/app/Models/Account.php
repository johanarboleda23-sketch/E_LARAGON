<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'balance',
        'currency',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function financialMovements()
    {
        return $this->hasMany(FinancialMovement::class);
    }
}
