<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BankReconciliationService
{
    public function __construct(private readonly AccountingPeriodService $periodService) {}

    public function matchPayment(Payment $payment, ?int $expectedStatementId = null): bool
    {
        if (! $payment->payment_method_id || ! $payment->reference) {
            return false;
        }

        return DB::transaction(function () use ($payment, $expectedStatementId): bool {
            $transactionType = $this->transactionType($payment);
            if (! $transactionType) {
                return false;
            }

            $samePayments = $this->paymentsForMatch($payment, $transactionType);
            if ($samePayments->count() !== 1) {
                return false;
            }

            $statements = $this->statementsForMatch($payment, $transactionType)
                ->lockForUpdate()
                ->get();

            if ($statements->count() !== 1 || ($expectedStatementId && $statements->first()->id !== $expectedStatementId)) {
                return false;
            }

            try {
                $this->periodService->assertWritable('bank', $statements->first()->transaction_date->toDateString());
            } catch (HttpException) {
                return false;
            }

            $this->reconcile($statements->first(), $payment);

            return true;
        });
    }

    public function matchStatement(BankStatement $statement): bool
    {
        if (! $statement->payment_method_id || ! $statement->reference) {
            return false;
        }

        return DB::transaction(function () use ($statement): bool {
            $lockedStatement = BankStatement::query()->lockForUpdate()->findOrFail($statement->id);
            if ($lockedStatement->status !== 'pending') {
                return false;
            }

            try {
                $this->periodService->assertWritable('bank', $lockedStatement->transaction_date->toDateString());
            } catch (HttpException) {
                return false;
            }

            $paymentType = $lockedStatement->transaction_type === 'credit'
                ? Sale::class
                : null;
            $payableTypes = $paymentType
                ? [Sale::class]
                : [Purchase::class, SupportDocument::class];

            $payments = $this->matchingPaymentsForStatement($lockedStatement, $payableTypes)->lockForUpdate()->get();
            $statements = $this->matchingStatementsForStatement($lockedStatement)->lockForUpdate()->get();

            if ($payments->count() !== 1 || $statements->count() !== 1) {
                return false;
            }

            $this->reconcile($lockedStatement, $payments->first());

            return true;
        });
    }

    private function transactionType(Payment $payment): ?string
    {
        return match ($payment->payable_type) {
            Sale::class => 'credit',
            Purchase::class, SupportDocument::class => 'debit',
            default => null,
        };
    }

    private function paymentsForMatch(Payment $payment, string $transactionType): Builder
    {
        return Payment::query()
            ->where('payment_method_id', $payment->payment_method_id)
            ->where('reference', $payment->reference)
            ->where('amount', $payment->amount)
            ->whereIn('payable_type', $this->payableTypes($transactionType))
            ->whereNotExists(fn (QueryBuilder $query) => $query
                ->selectRaw('1')
                ->from('bank_statements')
                ->whereColumn('bank_statements.matched_id', 'payments.id')
                ->where('bank_statements.matched_type', 'payment')
                ->where('bank_statements.status', 'reconciled'));
    }

    /**
     * @param  array<int, class-string>  $payableTypes
     */
    private function matchingPaymentsForStatement(BankStatement $statement, array $payableTypes): Builder
    {
        return Payment::query()
            ->where('payment_method_id', $statement->payment_method_id)
            ->where('reference', $statement->reference)
            ->where('amount', $statement->amount)
            ->whereIn('payable_type', $payableTypes)
            ->whereNotExists(fn (QueryBuilder $query) => $query
                ->selectRaw('1')
                ->from('bank_statements')
                ->whereColumn('bank_statements.matched_id', 'payments.id')
                ->where('bank_statements.matched_type', 'payment')
                ->where('bank_statements.status', 'reconciled'));
    }

    private function statementsForMatch(Payment $payment, string $transactionType): Builder
    {
        return BankStatement::query()
            ->where('payment_method_id', $payment->payment_method_id)
            ->where('reference', $payment->reference)
            ->where('amount', $payment->amount)
            ->where('transaction_type', $transactionType)
            ->where('status', 'pending');
    }

    private function matchingStatementsForStatement(BankStatement $statement): Builder
    {
        return BankStatement::query()
            ->where('payment_method_id', $statement->payment_method_id)
            ->where('reference', $statement->reference)
            ->where('amount', $statement->amount)
            ->where('transaction_type', $statement->transaction_type)
            ->where('status', 'pending');
    }

    /**
     * @return array<int, class-string>
     */
    private function payableTypes(string $transactionType): array
    {
        return $transactionType === 'credit'
            ? [Sale::class]
            : [Purchase::class, SupportDocument::class];
    }

    private function reconcile(BankStatement $statement, Payment $payment): void
    {
        $statement->update([
            'status' => 'reconciled',
            'matched_type' => 'payment',
            'matched_id' => $payment->id,
            'accounting_voucher_id' => $payment->accounting_voucher_id,
            'reconciled_at' => now(),
            'reconciled_by' => auth()->id(),
        ]);
    }
}
