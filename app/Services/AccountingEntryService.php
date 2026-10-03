<?php

namespace App\Services;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\CommercialDocument;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
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
     * Causa automáticamente una venta. Devuelve el comprobante generado, o null junto con
     * un motivo legible (vía $skipReason) cuando falta alguna cuenta PUC requerida.
     */
    public function postSale(Sale $sale, ?string &$skipReason = null): ?AccountingVoucher
    {
        $receivableAccountId = ChartOfAccount::query()->where('code', '1305')->value('id');
        if (! $receivableAccountId) {
            $skipReason = 'Falta la cuenta PUC 1305 (Clientes) para contabilizar la venta.';

            return null;
        }

        $incomeAccountId = ChartOfAccount::query()->where('code', '4135')->value('id');
        if (! $incomeAccountId) {
            $skipReason = 'Falta la cuenta PUC 4135 (Ingresos por ventas) para contabilizar la venta.';

            return null;
        }

        $lines = collect([[
            'chart_of_account_id' => $receivableAccountId,
            'detail' => 'Clientes '.$sale->customer_name,
            'debit' => (float) $sale->total,
            'credit' => 0,
        ], [
            'chart_of_account_id' => $incomeAccountId,
            'detail' => 'Ingreso por venta',
            'debit' => 0,
            'credit' => round((float) $sale->subtotal - (float) $sale->discount_total, 2),
        ]]);

        if ((float) $sale->iva_total > 0) {
            $ivaAccountId = ChartOfAccount::query()->where('code', '2408')->value('id');
            if (! $ivaAccountId) {
                $skipReason = 'Falta la cuenta PUC 2408 (Impuesto sobre las ventas por pagar) para contabilizar el IVA generado.';

                return null;
            }
            $lines->push(['chart_of_account_id' => $ivaAccountId, 'detail' => 'IVA generado', 'debit' => 0, 'credit' => (float) $sale->iva_total]);
        }

        if ((float) $sale->retention_total > 0) {
            $retentionAccountId = ChartOfAccount::query()->where('code', '1355')->value('id');
            if (! $retentionAccountId) {
                $skipReason = 'Falta la cuenta PUC 1355 (Anticipo de impuestos y contribuciones) para contabilizar la retención que te practicó el cliente.';

                return null;
            }
            $lines->push(['chart_of_account_id' => $retentionAccountId, 'detail' => 'Retención en la fuente practicada por el cliente', 'debit' => (float) $sale->retention_total, 'credit' => 0]);
        }

        $voucher = $this->post([
            'voucher_type' => 'venta',
            'consecutive' => 'VTA-'.$sale->invoice_number,
            'voucher_date' => $sale->sale_date,
            'third_party' => $sale->customer_name,
            'description' => 'Causación automática de venta '.$sale->invoice_number,
        ], $lines);

        $sale->update(['accounting_voucher_id' => $voucher->id]);

        return $voucher;
    }

    /**
     * Causa automáticamente un documento soporte. Devuelve el comprobante generado, o null junto
     * con un motivo legible (vía $skipReason) cuando falta alguna cuenta PUC requerida.
     */
    public function postSupportDocument(SupportDocument $document, ?string &$skipReason = null): ?AccountingVoucher
    {
        $document->loadMissing('supplier');

        $expenseAccountId = ChartOfAccount::query()->where('code', '5195')->value('id');
        if (! $expenseAccountId) {
            $skipReason = 'Falta la cuenta PUC 5195 (Gastos diversos) para contabilizar el documento soporte.';

            return null;
        }

        $payableAccountId = ChartOfAccount::query()->where('code', '2205')->value('id');
        if (! $payableAccountId) {
            $skipReason = 'Falta la cuenta PUC 2205 (Proveedores nacionales) para contabilizar el documento soporte.';

            return null;
        }

        $lines = collect([[
            'chart_of_account_id' => $expenseAccountId,
            'detail' => $document->concept,
            'debit' => round((float) $document->subtotal + (float) $document->iva_total, 2),
            'credit' => 0,
        ]]);

        if ((float) $document->retention_total > 0) {
            $retentionAccountId = ChartOfAccount::query()->where('code', '2365')->value('id');
            if (! $retentionAccountId) {
                $skipReason = 'Falta la cuenta PUC 2365 (Retención en la fuente) para contabilizar la retención calculada en este documento soporte.';

                return null;
            }
            $lines->push(['chart_of_account_id' => $retentionAccountId, 'detail' => 'Retención en la fuente', 'debit' => 0, 'credit' => (float) $document->retention_total]);
        }

        $lines->push(['chart_of_account_id' => $payableAccountId, 'detail' => 'Proveedor '.$document->supplier->name, 'debit' => 0, 'credit' => (float) $document->total]);

        $voucher = $this->post([
            'voucher_type' => 'documento_soporte',
            'consecutive' => 'DS-'.$document->consecutive,
            'voucher_date' => $document->document_date,
            'third_party' => $document->supplier->name,
            'description' => 'Causación automática de documento soporte '.$document->consecutive,
        ], $lines);

        $document->update(['accounting_voucher_id' => $voucher->id]);

        return $voucher;
    }

    /**
     * Causa automáticamente una nómina. Devuelve el comprobante generado, o null junto con
     * un motivo legible (vía $skipReason) cuando falta alguna cuenta PUC requerida.
     */
    public function postPayroll(PayrollRun $run, ?string &$skipReason = null): ?AccountingVoucher
    {
        $run->loadMissing('lines');

        if ($run->lines->isEmpty()) {
            $skipReason = 'La nómina no tiene empleados calculados.';

            return null;
        }

        $codes = [
            'salary_expense' => '5105',
            'social_benefits_expense' => '5130',
            'employer_contributions_expense' => '5135',
            'withholding_payable' => '2370',
            'contributions_payable' => '2380',
            'social_benefits_payable' => '2610',
            'net_pay_payable' => '2505',
        ];
        $accountIds = [];
        $labels = [
            'salary_expense' => 'Gastos de personal (salarios)',
            'social_benefits_expense' => 'Prestaciones sociales (gasto)',
            'employer_contributions_expense' => 'Aportes sobre la nómina (gasto)',
            'withholding_payable' => 'Retención en la fuente por pagar',
            'contributions_payable' => 'Aportes de seguridad social por pagar',
            'social_benefits_payable' => 'Prestaciones sociales por pagar',
            'net_pay_payable' => 'Salarios por pagar',
        ];
        foreach ($codes as $key => $code) {
            $accountId = ChartOfAccount::query()->where('code', $code)->value('id');
            if (! $accountId) {
                $skipReason = "Falta la cuenta PUC {$code} ({$labels[$key]}) para contabilizar la nómina.";

                return null;
            }
            $accountIds[$key] = $accountId;
        }

        $grossTotal = round((float) $run->lines->sum('salary'), 2);
        $provisionsTotal = round((float) $run->lines->sum(fn ($line) => $line->severance_provision + $line->service_bonus_provision + $line->vacation_provision), 2);
        $employerContributionsTotal = round((float) $run->lines->sum(fn ($line) => $line->health_employer + $line->pension_employer + $line->arl_employer + $line->parafiscals), 2);
        $employeeContributionsTotal = round((float) $run->lines->sum(fn ($line) => $line->health_employee + $line->pension_employee + $line->solidarity_employee), 2);
        $withholdingTotal = round((float) $run->lines->sum('withholding'), 2);
        $netPayTotal = round((float) $run->lines->sum('net_pay'), 2);

        $lines = collect([
            ['chart_of_account_id' => $accountIds['salary_expense'], 'detail' => 'Salarios del período '.$run->period, 'debit' => $grossTotal, 'credit' => 0],
        ]);

        if ($provisionsTotal > 0) {
            $lines->push(['chart_of_account_id' => $accountIds['social_benefits_expense'], 'detail' => 'Provisión cesantías, prima y vacaciones', 'debit' => $provisionsTotal, 'credit' => 0]);
        }
        if ($employerContributionsTotal > 0) {
            $lines->push(['chart_of_account_id' => $accountIds['employer_contributions_expense'], 'detail' => 'Aportes patronales y parafiscales', 'debit' => $employerContributionsTotal, 'credit' => 0]);
        }
        if ($withholdingTotal > 0) {
            $lines->push(['chart_of_account_id' => $accountIds['withholding_payable'], 'detail' => 'Retención en la fuente de empleados', 'debit' => 0, 'credit' => $withholdingTotal]);
        }
        if ($employeeContributionsTotal + $employerContributionsTotal > 0) {
            $lines->push(['chart_of_account_id' => $accountIds['contributions_payable'], 'detail' => 'Aportes de seguridad social por pagar', 'debit' => 0, 'credit' => round($employeeContributionsTotal + $employerContributionsTotal, 2)]);
        }
        if ($provisionsTotal > 0) {
            $lines->push(['chart_of_account_id' => $accountIds['social_benefits_payable'], 'detail' => 'Cesantías, prima y vacaciones por pagar', 'debit' => 0, 'credit' => $provisionsTotal]);
        }
        $lines->push(['chart_of_account_id' => $accountIds['net_pay_payable'], 'detail' => 'Nómina neta por pagar', 'debit' => 0, 'credit' => $netPayTotal]);

        $voucher = $this->post([
            'voucher_type' => 'nomina',
            'consecutive' => 'NOM-'.$run->period.'-'.$run->id,
            'voucher_date' => $run->payment_date,
            'third_party' => null,
            'description' => 'Causación automática de nómina del período '.$run->period,
        ], $lines);

        $run->update(['accounting_voucher_id' => $voucher->id]);

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
