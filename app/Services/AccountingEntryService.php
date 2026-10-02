<?php

namespace App\Services;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\CommercialDocument;
use App\Models\Purchase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingEntryService
{
    /**
     * Causa automáticamente una compra. Devuelve el comprobante generado, o null junto con
     * un motivo legible (vía $skipReason) cuando falta alguna cuenta PUC o forma de pago.
     */
    public function postPurchase(Purchase $purchase, ?string &$skipReason = null): ?AccountingVoucher
    {
        $purchase->load(['details.item', 'paymentMethod.account']);
        $paymentAccountId = $purchase->paymentMethod?->chart_of_account_id;

        if (! $purchase->paymentMethod) {
            $skipReason = 'Selecciona una forma de pago en la factura para poder contabilizarla.';

            return null;
        }

        if (! $paymentAccountId) {
            $skipReason = "La forma de pago '{$purchase->paymentMethod->name}' no tiene una cuenta PUC asociada. Configúrala en Administración.";

            return null;
        }

        $lines = collect();
        foreach ($purchase->details as $detail) {
            $debitAccountId = match ($detail->purchase_line_type ?? 'producto') {
                'gasto', 'activo_fijo' => $detail->chart_of_account_id,
                default => ChartOfAccount::query()->where('code', '1435')->value('id'),
            };

            if (! $debitAccountId) {
                $skipReason = 'Falta la cuenta PUC 1435 (Mercancías no fabricadas por la empresa) para contabilizar el inventario comprado.';

                return null;
            }

            $lines->push([
                'chart_of_account_id' => $debitAccountId,
                'detail' => $detail->line_description ?: $detail->item?->name ?: 'Compra',
                'debit' => (float) $detail->quantity * (float) $detail->cost_price,
                'credit' => 0,
            ]);
        }

        if ((float) $purchase->iva_total > 0) {
            $ivaAccountId = ChartOfAccount::query()->where('code', '2408')->value('id');
            if (! $ivaAccountId) {
                $skipReason = 'Falta la cuenta PUC 2408 (Impuesto sobre las ventas por pagar) para contabilizar el IVA descontable.';

                return null;
            }
            $lines->push(['chart_of_account_id' => $ivaAccountId, 'detail' => 'IVA descontable', 'debit' => (float) $purchase->iva_total, 'credit' => 0]);
        }

        $creditTotal = (float) $purchase->total_pagar;
        if ((float) $purchase->retefuente > 0) {
            $retentionAccountId = ChartOfAccount::query()->where('code', '2365')->value('id');
            if (! $retentionAccountId) {
                $skipReason = 'Falta la cuenta PUC 2365 (Retención en la fuente) para contabilizar la retención calculada en esta compra.';

                return null;
            }
            $lines->push(['chart_of_account_id' => $retentionAccountId, 'detail' => 'Retención en la fuente', 'debit' => 0, 'credit' => (float) $purchase->retefuente]);
        }

        $lines->push(['chart_of_account_id' => $paymentAccountId, 'detail' => 'Forma de pago '.$purchase->paymentMethod->name, 'debit' => 0, 'credit' => $creditTotal]);

        $voucher = $this->post([
            'voucher_type' => 'compra',
            'consecutive' => 'COMP-'.$purchase->invoice_number,
            'voucher_date' => $purchase->purchase_date,
            'third_party' => $purchase->provider,
            'description' => 'Causación automática de compra '.$purchase->invoice_number,
        ], $lines);

        $purchase->update(['accounting_voucher_id' => $voucher->id]);

        return $voucher;
    }

    /**
     * @param  Collection<int, array{chart_of_account_id: int, detail?: ?string, debit: float, credit: float}>  $lines
     */
    public function post(array $data, Collection $lines, ?CommercialDocument $commercialDocument = null): AccountingVoucher
    {
        $normalizedLines = $lines->map(function (array $line): array {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                throw ValidationException::withMessages([
                    'lines' => 'Cada línea debe tener solo un valor débito o crédito.',
                ]);
            }

            abort_unless(
                ChartOfAccount::query()
                    ->whereKey($line['chart_of_account_id'])
                    ->where('active', true)
                    ->where('allows_posting', true)
                    ->exists(),
                422,
                'Todas las líneas deben usar cuentas auxiliares activas del PUC.',
            );

            return [
                'chart_of_account_id' => (int) $line['chart_of_account_id'],
                'detail' => $line['detail'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
            ];
        });

        $totalDebit = round($normalizedLines->sum('debit'), 2);
        $totalCredit = round($normalizedLines->sum('credit'), 2);

        if ($totalDebit <= 0 || $totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'lines' => 'El comprobante debe cuadrar: débito y crédito deben ser iguales y mayores que cero.',
            ]);
        }

        return DB::transaction(function () use ($data, $normalizedLines, $totalDebit, $totalCredit, $commercialDocument): AccountingVoucher {
            if ($commercialDocument) {
                $commercialDocument = CommercialDocument::query()->lockForUpdate()->findOrFail($commercialDocument->id);
                abort_if($commercialDocument->status !== 'draft' || $commercialDocument->accountingVoucher()->exists(), 409);
            }

            $voucher = AccountingVoucher::create([
                'voucher_type' => $data['voucher_type'],
                'consecutive' => $data['consecutive'],
                'voucher_date' => $data['voucher_date'],
                'third_party' => $commercialDocument?->third_party_name ?? ($data['third_party'] ?? null),
                'description' => $data['description'] ?? null,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'commercial_document_id' => $commercialDocument?->id,
                'created_by' => auth()->id(),
            ]);

            $voucher->lines()->createMany($normalizedLines->all());

            if ($commercialDocument) {
                $commercialDocument->update([
                    'status' => 'accounted',
                    'accounted_at' => now(),
                ]);
            }

            return $voucher;
        });
    }
}
