<?php

namespace App\Http\Controllers;

use App\Models\AccountingVoucher;
use App\Models\AccountingVoucherLine;
use App\Models\ChartOfAccount;
use App\Models\CommercialDocument;
use App\Models\ThirdParty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AccountingVoucherController extends Controller
{
    public function index(Request $request)
    {
        $commercialDocument = null;
        $voucherPrefill = [];

        if ($request->filled('commercial_document_id')) {
            $commercialDocument = CommercialDocument::query()->findOrFail($request->integer('commercial_document_id'));
            $voucherType = match ($commercialDocument->document_type) {
                'customer_credit_note' => 'nota_credito_cliente',
                'supplier_debit_note' => 'nota_debito_proveedor',
                default => abort(404),
            };

            if ($commercialDocument->accountingVoucher) {
                return redirect()->route('accounting.vouchers.accounting', $commercialDocument->accountingVoucher);
            }

            abort_if($commercialDocument->status !== 'draft', 409);

            $voucherPrefill = [
                'voucher_type' => $voucherType,
                'voucher_date' => $commercialDocument->document_date->toDateString(),
                'consecutive' => 'CONT-'.$commercialDocument->consecutive,
                'third_party' => $commercialDocument->third_party_name,
                'description' => 'Contabilización manual de '.$commercialDocument->consecutive,
                'amount' => number_format((float) $commercialDocument->total, 2, '.', ''),
            ];
        }

        $accounts = ChartOfAccount::where('active', true)
            ->where('allows_posting', true)
            ->orderBy('code')
            ->get();
        $vouchers = AccountingVoucher::with(['creator', 'lines.account'])
            ->latest()
            ->take(20)
            ->get();
        $customers = ThirdParty::where('is_customer', true)->where('active', true)->orderBy('name')->get();
        $suppliers = ThirdParty::where('is_supplier', true)->where('active', true)->orderBy('name')->get();

        return view('accounting.vouchers.index', compact('accounts', 'vouchers', 'customers', 'suppliers', 'commercialDocument', 'voucherPrefill'));
    }

    public function store(Request $request)
    {
        $commercialDocument = null;
        if ($request->filled('commercial_document_id') && filter_var($request->input('commercial_document_id'), FILTER_VALIDATE_INT) !== false) {
            $commercialDocument = CommercialDocument::query()->findOrFail($request->integer('commercial_document_id'));
        }

        $data = $request->validate([
            'voucher_type' => ['required', Rule::in(['egreso', 'recibo_caja', 'ajuste_contable', 'nomina', 'seguridad_social', 'provision_empleados', 'nota_credito_cliente', 'nota_debito_proveedor'])],
            'voucher_date' => 'required|date',
            'consecutive' => 'required|string|max:50|unique:accounting_vouchers,consecutive',
            'commercial_document_id' => ['nullable', 'integer', Rule::requiredIf(in_array($request->input('voucher_type'), ['nota_credito_cliente', 'nota_debito_proveedor'], true))],
            'third_party' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:2',
            'lines.*.chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.detail' => 'nullable|string|max:255',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
        ]);

        $lines = collect($data['lines'])->map(function (array $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                abort(422, 'Cada línea debe tener solo un valor débito o crédito.');
            }

            return [...$line, 'debit' => $debit, 'credit' => $credit];
        });

        $totalDebit = round($lines->sum('debit'), 2);
        $totalCredit = round($lines->sum('credit'), 2);
        if ($totalDebit <= 0 || $totalDebit !== $totalCredit) {
            return back()->withInput()->withErrors(['lines' => 'El comprobante debe cuadrar: débito y crédito deben ser iguales y mayores que cero.']);
        }

        if ($request->filled('commercial_document_id')) {
            $commercialDocument ??= CommercialDocument::query()->findOrFail($request->integer('commercial_document_id'));
            $expectedDocumentType = match ($data['voucher_type']) {
                'nota_credito_cliente' => 'customer_credit_note',
                'nota_debito_proveedor' => 'supplier_debit_note',
                default => abort(422, 'El comprobante indicado no admite una nota comercial.'),
            };

            abort_unless($commercialDocument->document_type === $expectedDocumentType, 422, 'El tipo de comprobante no corresponde a la nota.');
            abort_if($commercialDocument->status !== 'draft' || $commercialDocument->accountingVoucher()->exists(), 409);

            if (round($totalDebit, 2) !== round((float) $commercialDocument->total, 2)) {
                return back()->withInput()->withErrors(['lines' => 'El débito y el crédito deben coincidir con el total de la nota.']);
            }

            foreach ($lines as $line) {
                if (! ChartOfAccount::query()->whereKey($line['chart_of_account_id'])->exists()) {
                    return back()->withInput()->withErrors(['lines' => 'Selecciona cuentas PUC activas de la empresa actual.']);
                }
            }
        }

        $voucher = DB::transaction(function () use ($data, $lines, $totalDebit, $totalCredit, $commercialDocument): AccountingVoucher {
            if ($commercialDocument) {
                $commercialDocument = CommercialDocument::query()->lockForUpdate()->findOrFail($commercialDocument->id);
                abort_if($commercialDocument->status !== 'draft' || $commercialDocument->accountingVoucher()->exists(), 409);
                abort_unless(round($totalDebit, 2) === round((float) $commercialDocument->total, 2), 409);
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

            foreach ($lines as $line) {
                AccountingVoucherLine::create([
                    'accounting_voucher_id' => $voucher->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'detail' => $line['detail'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            if ($commercialDocument) {
                $commercialDocument->update([
                    'status' => 'accounted',
                    'accounted_at' => now(),
                ]);
            }

            return $voucher;
        });

        return $commercialDocument
            ? redirect()->route('accounting.vouchers.accounting', $voucher)->with('success', 'Nota contabilizada correctamente.')
            : redirect()->route('accounting.vouchers.index')->with('success', 'Comprobante contable guardado correctamente.');
    }

    public function destroy(AccountingVoucher $voucher)
    {
        abort_if($voucher->commercial_document_id, 409, 'No se puede eliminar un comprobante vinculado a una nota contabilizada.');
        $voucher->update(['deleted_by' => auth()->id()]);
        $voucher->delete();

        return back()->with('success', 'Comprobante eliminado y conservado en auditoría.');
    }

    public function accounting(AccountingVoucher $voucher)
    {
        return view('accounting.vouchers.accounting', compact('voucher'));
    }

    public function statement(AccountingVoucher $voucher)
    {
        return view('accounting.vouchers.statement', compact('voucher'));
    }

    public function email(Request $request, AccountingVoucher $voucher)
    {
        $data = $request->validate(['email' => 'required|email']);
        abort_unless(in_array($voucher->voucher_type, ['egreso', 'recibo_caja'], true), 422, 'Solo recibos de caja y egresos pueden enviarse por correo.');
        $voucher->load('lines.account');
        $body = "Comprobante: {$voucher->consecutive}\nTipo: {$voucher->voucher_type}\nFecha: {$voucher->voucher_date}\nTercero: {$voucher->third_party}\nTotal: {$voucher->total_debit}\n\n{$voucher->description}\n\nDetalle:\n";
        foreach ($voucher->lines as $line) {
            $body .= $line->account->code.' - '.$line->account->name.' | Debito: '.$line->debit.' | Credito: '.$line->credit."\n";
        }
        Mail::raw($body, function ($message) use ($data, $voucher) {
            $message->to($data['email'])->subject('Comprobante '.$voucher->consecutive);
        });

        return back()->with('success', 'Comprobante enviado al correo del tercero.');
    }
}
