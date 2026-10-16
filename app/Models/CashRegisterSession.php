<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\CashRegisterMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    use BelongsToCompany;

    public const FUND_TYPES = [
        'general' => 'Caja general',
        'menor' => 'Caja menor',
    ];

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashRegisterMovement::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(CashRegisterExpense::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Calcula el efectivo esperado a partir de los comprobantes de recibo de caja (entradas) y
     * egreso (salidas) registrados entre la apertura y el corte (ahora si sigue abierta).
     */
    public function recalculateExpectedCash(): void
    {
        $cutoff = $this->closed_at ?? now();

        $cashIn = (float) AccountingVoucher::query()
            ->where('voucher_type', 'recibo_caja')
            ->whereDate('voucher_date', '>=', $this->opened_at->toDateString())
            ->whereDate('voucher_date', '<=', $cutoff->toDateString())
            ->whereNotIn('id', CashRegisterMovement::query()
                ->where('payment_method', 'bank')
                ->whereNotNull('accounting_voucher_id')
                ->select('accounting_voucher_id'))
            ->sum('total_debit');

        $cashOut = (float) AccountingVoucher::query()
            ->where('voucher_type', 'egreso')
            ->whereDate('voucher_date', '>=', $this->opened_at->toDateString())
            ->whereDate('voucher_date', '<=', $cutoff->toDateString())
            ->whereNotIn('id', CashRegisterMovement::query()
                ->where('payment_method', 'bank')
                ->whereNotNull('accounting_voucher_id')
                ->select('accounting_voucher_id'))
            ->sum('total_debit');

        $movementCashIn = (float) $this->movements()
            ->whereIn('payment_method', ['cash', '1105'])
            ->whereIn('movement_type', ['collection', 'receivable_collection', 'exchange_difference'])
            ->whereNull('accounting_voucher_id')
            ->sum('amount');
        $expenseTotal = (float) $this->expenses()->whereNull('accounting_voucher_id')->sum('amount');

        $this->cash_in = $cashIn + $movementCashIn;
        $this->cash_out = $cashOut + $expenseTotal;
        $this->expected_cash = round((float) $this->opening_balance + $cashIn + $movementCashIn - $cashOut - $expenseTotal, 2);
    }
}
