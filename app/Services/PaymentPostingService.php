<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;

class PaymentPostingService
{
    public function __construct(
        private readonly AccountingEntryService $accountingEntryService,
    ) {}

    public function post(
        Sale|Purchase|SupportDocument $document,
        PaymentMethod $paymentMethod,
        ChartOfAccount $counterAccount,
        float $amount,
        float $withholdingTotal,
        array $withholdings,
        string $paymentDate,
        ?string $reference,
        ?string $notes,
    ): Payment {
        $payment = $document->payments()->create([
            'amount' => $amount,
            'withholding_total' => $withholdingTotal,
            'payment_date' => $paymentDate,
            'method' => $paymentMethod->name,
            'payment_method_id' => $paymentMethod->id,
            'reference' => $reference,
            'notes' => $notes,
            'created_by' => auth()->id(),
        ]);

        $party = match (true) {
            $document instanceof Sale => $document->customer_name,
            $document instanceof Purchase => $document->provider,
            default => $document->supplier?->name ?? 'Proveedor',
        };
        $isIncome = $document instanceof Sale;
        $bankLine = [
            'chart_of_account_id' => $paymentMethod->chart_of_account_id,
            'detail' => ($isIncome ? 'Ingreso por pago de ' : 'Pago a ').$party,
            'debit' => $isIncome ? $amount : 0,
            'credit' => $isIncome ? 0 : $amount,
        ];
        $counterLine = [
            'chart_of_account_id' => $counterAccount->id,
            'detail' => ($isIncome ? 'Abono de cliente ' : 'Abono a proveedor ').$party,
            'debit' => $isIncome ? 0 : $amount + $withholdingTotal,
            'credit' => $isIncome ? $amount + $withholdingTotal : 0,
        ];
        $retentionLines = collect($withholdings)->map(fn (array $withholding): array => [
            'chart_of_account_id' => $withholding['account']->id,
            'detail' => 'Retención practicada al momento del pago · '.$party,
            'debit' => $isIncome ? $withholding['amount'] : 0,
            'credit' => $isIncome ? 0 : $withholding['amount'],
        ]);
        $voucher = $this->accountingEntryService->post([
            'voucher_type' => $isIncome ? 'recibo_caja' : 'egreso',
            'consecutive' => ($isIncome ? 'RC-PAG-' : 'EG-PAG-').$payment->id,
            'voucher_date' => $paymentDate,
            'third_party' => $party,
            'description' => 'Pago de '.match (true) {
                $document instanceof Sale => 'factura '.$document->invoice_number,
                $document instanceof Purchase => 'compra '.$document->invoice_number,
                default => 'documento soporte '.$document->consecutive,
            },
        ], collect([$bankLine, ...$retentionLines->all(), $counterLine]));

        $payment->update(['accounting_voucher_id' => $voucher->id]);

        return $payment->fresh()->load('accountingVoucher');
    }
}
