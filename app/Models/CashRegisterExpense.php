<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterExpense extends Model
{
    use BelongsToCompany;

    public const CATEGORIES = [
        'combustible' => 'Combustible',
        'pago_proveedor' => 'Pago a proveedores',
        'transferencia_interna' => 'Transferencia a bancos internos',
        'otro' => 'Otro gasto',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
