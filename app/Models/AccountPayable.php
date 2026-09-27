<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AccountPayable extends Model
{
    use BelongsToCompany;
    //
}
