<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use BelongsToCompany;

    protected $guarded = [];
}
