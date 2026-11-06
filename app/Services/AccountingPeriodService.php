<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\AccountingPeriodEvent;
use App\Models\BankStatement;
use App\Models\CashRegisterSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AccountingPeriodService
{
    public const LOCKED_MODULES = ['purchases', 'income', 'expenses', 'bank'];

    public function assertWritable(string $module, string $date): void
    {
        if (! in_array($module, self::LOCKED_MODULES, true)) {
            return;
        }

        $transactionDate = CarbonImmutable::parse($date);
        $periods = [
            ['month', $transactionDate->format('Y-m')],
            ['year', $transactionDate->format('Y')],
        ];

        foreach ($periods as [$type, $key]) {
            $period = AccountingPeriod::query()
                ->where('period_type', $type)
                ->where('period_key', $key)
                ->where('status', 'closed')
                ->first();

            if ($period && in_array($module, $period->locked_modules ?? [], true)) {
                abort(409, 'El periodo contable está cerrado. Un administrador debe reabrirlo antes de modificar este movimiento.');
            }
        }
    }

    /**
     * @return array{pending_bank_movements: int, open_cash_sessions: int}
     */
    public function monthReadiness(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        return [
            'pending_bank_movements' => BankStatement::query()
                ->whereDate('transaction_date', '>=', $start->toDateString())
                ->whereDate('transaction_date', '<=', $end->toDateString())
                ->where('status', 'pending')
                ->count(),
            'open_cash_sessions' => CashRegisterSession::query()
                ->where('status', 'open')
                ->where('opened_at', '<=', $end->endOfDay())
                ->count(),
        ];
    }

    public function closeMonth(int $year, int $month, int $actorId): AccountingPeriod
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();
        abort_if($end->isFuture(), 422, 'Solo se puede cerrar un mes que ya terminó.');

        $readiness = $this->monthReadiness($year, $month);
        abort_if(
            $readiness['pending_bank_movements'] > 0,
            422,
            'No se puede cerrar el mes mientras tenga movimientos bancarios pendientes de conciliar.',
        );
        abort_if(
            $readiness['open_cash_sessions'] > 0,
            422,
            'No se puede cerrar el mes mientras haya sesiones de caja abiertas.',
        );

        return DB::transaction(function () use ($actorId, $start, $end): AccountingPeriod {
            $period = $this->findOrCreatePeriod('month', $start->format('Y-m'), $start, $end);
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            abort_if($period->isClosed(), 409, 'El mes ya está cerrado.');

            $period->forceFill([
                'status' => 'closed',
                'locked_modules' => self::LOCKED_MODULES,
                'closed_at' => now(),
                'closed_by' => $actorId,
            ])->save();
            $this->recordEvent($period, 'close', $actorId);

            return $period;
        });
    }

    public function closeYear(int $year, int $actorId): AccountingPeriod
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = CarbonImmutable::create($year, 12, 31)->endOfDay();
        abort_if($end->isFuture(), 422, 'Solo se puede cerrar un año que ya terminó.');

        $closedMonths = AccountingPeriod::query()
            ->where('period_type', 'month')
            ->where('status', 'closed')
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_start', '<=', $end->toDateString())
            ->count();
        abort_unless($closedMonths === 12, 422, 'Debes cerrar los doce meses antes de cerrar el año.');

        return DB::transaction(function () use ($year, $actorId, $start, $end): AccountingPeriod {
            $period = $this->findOrCreatePeriod('year', (string) $year, $start, $end);
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            abort_if($period->isClosed(), 409, 'El año ya está cerrado.');

            $period->forceFill([
                'status' => 'closed',
                'locked_modules' => self::LOCKED_MODULES,
                'closed_at' => now(),
                'closed_by' => $actorId,
            ])->save();
            $this->recordEvent($period, 'close', $actorId);

            return $period;
        });
    }

    public function reopen(AccountingPeriod $period, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($period, $actorId, $reason): void {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            abort_unless($period->isClosed(), 409, 'El periodo ya está abierto.');

            if ($period->period_type === 'month') {
                $yearPeriod = AccountingPeriod::query()
                    ->where('period_type', 'year')
                    ->where('period_key', substr($period->period_key, 0, 4))
                    ->where('status', 'closed')
                    ->exists();
                abort_if($yearPeriod, 409, 'Primero debes reabrir el cierre anual.');
            }

            $period->forceFill([
                'status' => 'open',
                'closed_at' => null,
                'closed_by' => null,
            ])->save();
            $this->recordEvent($period, 'reopen', $actorId, $reason);
        });
    }

    private function findOrCreatePeriod(string $type, string $key, CarbonImmutable $start, CarbonImmutable $end): AccountingPeriod
    {
        $period = AccountingPeriod::query()
            ->where('period_type', $type)
            ->where('period_key', $key)
            ->first();

        if ($period) {
            return $period;
        }

        return AccountingPeriod::create([
            'company_id' => session('company_id'),
            'period_type' => $type,
            'period_key' => $key,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => 'open',
            'locked_modules' => [],
        ]);
    }

    private function recordEvent(AccountingPeriod $period, string $action, int $actorId, ?string $reason = null): void
    {
        AccountingPeriodEvent::create([
            'company_id' => $period->company_id,
            'accounting_period_id' => $period->id,
            'action' => $action,
            'actor_id' => $actorId,
            'reason' => $reason,
        ]);
    }
}
