<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Calcula el efectivo esperado a partir de los comprobantes de recibo de caja (entradas) y
     * egreso (salidas) registrados entre la apertura y el corte (ahora si sigue abierta), más
     * los movimientos de caja capturados desde logística/facturas y menos los gastos del día.
     */
    public function recalculateExpectedCash(): void
    {
        $cutoff = $this->closed_at ?? now();
        $from = $this->opened_at->toDateString();
        $to = $cutoff->toDateString();

        $cashIn = (float) AccountingVoucher::query()
            ->where('voucher_type', 'recibo_caja')
            ->whereDate('voucher_date', '>=', $from)
            ->whereDate('voucher_date', '<=', $to)
            ->sum('total_debit');

        $cashOut = (float) AccountingVoucher::query()
            ->where('voucher_type', 'egreso')
            ->whereDate('voucher_date', '>=', $from)
            ->whereDate('voucher_date', '<=', $to)
            ->sum('total_debit');

        $movementsCash = (float) CashRegisterMovement::query()
            ->where('movement_type', 'cash')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('amount');

        $movementsBank = (float) CashRegisterMovement::query()
            ->where('movement_type', 'bank')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('amount');

        $movementsCredit = (float) CashRegisterMovement::query()
            ->where('movement_type', 'credit')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('amount');

        $expensesTotal = (float) CashRegisterExpense::query()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->sum('amount');

        $this->cash_in = $cashIn;
        $this->cash_out = $cashOut;
        $this->movements_cash = $movementsCash;
        $this->movements_bank = $movementsBank;
        $this->movements_credit = $movementsCredit;
        $this->expenses_total = $expensesTotal;
        $this->expected_cash = round(
            (float) $this->opening_balance + $cashIn + $movementsCash - $cashOut - $expensesTotal,
            2
        );
    }
}
