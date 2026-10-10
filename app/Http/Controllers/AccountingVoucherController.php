<?php

namespace App\Http\Controllers;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\CommercialDocument;
use App\Models\Company;
use App\Models\ThirdParty;
use App\Services\AccountingEntryService;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class AccountingVoucherController extends Controller
{
    public const VOUCHER_TYPES = [
        'egreso' => 'Egreso / Gasto',
        'recibo_caja' => 'Recibo de caja',
        'ajuste_contable' => 'Ajuste contable',
        'nomina' => 'Nómina',
        'seguridad_social' => 'Pago de seguridad social',
        'provision_empleados' => 'Provisión de empleados',
        'nota_credito_cliente' => 'Nota crédito cliente',
        'nota_credito_proveedor' => 'Nota crédito proveedor',
        'nota_debito_cliente' => 'Nota débito cliente',
        'nota_debito_proveedor' => 'Nota débito proveedor',
    ];

    /**
     * Prefijos para el consecutivo interno de control (no son resoluciones DIAN).
     *
     * @var array<string, string>
     */
    private const CONSECUTIVE_PREFIXES = [
        'egreso' => 'EG',
        'recibo_caja' => 'RC',
        'ajuste_contable' => 'AJ',
        'nomina' => 'NOM',
        'seguridad_social' => 'SS',
        'provision_empleados' => 'PROV',
        'nota_credito_cliente' => 'NCC',
        'nota_credito_proveedor' => 'NCP',
        'nota_debito_cliente' => 'NDC',
        'nota_debito_proveedor' => 'NDP',
    ];

    public function __construct(private readonly AccountingEntryService $accountingEntryService) {}

    /**
     * Genera el siguiente consecutivo interno (solo control, no requiere resolución DIAN).
     */
    public static function nextInternalConsecutive(string $voucherType): string
    {
        $prefix = self::CONSECUTIVE_PREFIXES[$voucherType] ?? strtoupper(substr($voucherType, 0, 3));
        $count = AccountingVoucher::query()->where('voucher_type', $voucherType)->count();

        return sprintf('%s-%05d', $prefix, $count + 1);
    }

    public function index(Request $request)
    {
        $fixedVoucherType = $request->route('voucherType');
        $commercialDocument = null;
        $voucherPrefill = [];

        if ($request->filled('commercial_document_id')) {
            $commercialDocument = CommercialDocument::query()->findOrFail($request->integer('commercial_document_id'));
            $noteVoucherType = match ($commercialDocument->document_type) {
                'customer_credit_note' => 'nota_credito_cliente',
                'supplier_debit_note' => 'nota_debito_proveedor',
                default => abort(404),
            };

            if ($commercialDocument->accountingVoucher) {
                return redirect()->route('accounting.vouchers.accounting', $commercialDocument->accountingVoucher);
            }

            abort_if($commercialDocument->status !== 'draft', 409);

            $voucherPrefill = [
                'voucher_type' => $noteVoucherType,
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
            ->when($fixedVoucherType, fn ($query) => $query->where('voucher_type', $fixedVoucherType))
            ->latest()
            ->take(20)
            ->get();
        $customers = ThirdParty::where('is_customer', true)->where('active', true)->orderBy('name')->get();
        $suppliers = ThirdParty::where('is_supplier', true)->where('active', true)->orderBy('name')->get();
        $voucherType = $fixedVoucherType;
        $voucherTypeLabel = $fixedVoucherType ? (self::VOUCHER_TYPES[$fixedVoucherType] ?? $fixedVoucherType) : null;
        $nextConsecutive = $fixedVoucherType ? self::nextInternalConsecutive($fixedVoucherType) : null;

        return view('accounting.vouchers.index', compact('accounts', 'vouchers', 'customers', 'suppliers', 'commercialDocument', 'voucherPrefill', 'voucherType', 'voucherTypeLabel', 'nextConsecutive'));
    }

    public function store(Request $request)
    {
        $fixedVoucherType = $request->route('voucherType');
        if ($fixedVoucherType) {
            $request->merge(['voucher_type' => $fixedVoucherType]);
        }

        $commercialDocument = null;
        if ($request->filled('commercial_document_id') && filter_var($request->input('commercial_document_id'), FILTER_VALIDATE_INT) !== false) {
            $commercialDocument = CommercialDocument::query()->findOrFail($request->integer('commercial_document_id'));
        }

        $data = $request->validate([
            'voucher_type' => ['required', Rule::in(array_keys(self::VOUCHER_TYPES))],
            'voucher_date' => 'required|date',
            'consecutive' => [$fixedVoucherType ? 'nullable' : 'required', 'string', 'max:50', Rule::unique('accounting_vouchers', 'consecutive')->where('company_id', session('company_id'))],
            'commercial_document_id' => ['nullable', 'integer', Rule::requiredIf(in_array($request->input('voucher_type'), ['nota_credito_cliente', 'nota_debito_proveedor'], true))],
            'third_party' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:2',
            'lines.*.chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.detail' => 'nullable|string|max:255',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
        ]);

        if ($fixedVoucherType) {
            $data['consecutive'] = self::nextInternalConsecutive($fixedVoucherType);
        }

        $lines = collect($data['lines']);

        $totalDebit = round($lines->sum(fn (array $line): float => (float) ($line['debit'] ?? 0)), 2);
        $totalCredit = round($lines->sum(fn (array $line): float => (float) ($line['credit'] ?? 0)), 2);
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

        }

        $voucher = $this->accountingEntryService->post($data, $lines, $commercialDocument);

        return $commercialDocument
            ? redirect()->route('accounting.vouchers.accounting', $voucher)->with('success', 'Nota contabilizada correctamente.')
            : back()->with('success', 'Comprobante contable guardado correctamente.');
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
        $voucher->load('lines.account', 'creator');
        $company = Company::find(session('company_id'));

        return view('accounting.vouchers.accounting', compact('voucher', 'company'));
    }

    /**
     * Devuelve el comprobante en JSON para mostrarlo en el modal de "asiento contable"
     * reutilizado por los demás módulos (Compras, Ventas, Nómina, Documento soporte).
     */
    public function json(AccountingVoucher $voucher)
    {
        $voucher->load('lines.account');

        return response()->json([
            'consecutive' => $voucher->consecutive,
            'voucher_type' => $voucher->voucher_type,
            'voucher_date' => $voucher->voucher_date->toDateString(),
            'third_party' => $voucher->third_party,
            'description' => $voucher->description,
            'total_debit' => (float) $voucher->total_debit,
            'total_credit' => (float) $voucher->total_credit,
            'lines' => $voucher->lines->map(fn ($line) => [
                'account' => $line->account->code.' - '.$line->account->name,
                'detail' => $line->detail,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
            ]),
        ]);
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

    public function importTemplate(): Response
    {
        $rows = [
            ['Fecha', 'Tercero/Proveedor', 'Concepto', 'Valor del servicio', 'Cuenta gasto (código PUC)', 'Cuenta por pagar (código PUC)', 'IVA %', 'Cuenta IVA descontable (código PUC)', 'Retención %', 'Cuenta retención por pagar (código PUC)'],
            [now()->toDateString(), 'Contratista Ejemplo S.A.S.', 'Servicios de consultoría', '1000000', '513530', '220525', '19', '240805', '11', '236540'],
        ];

        $content = collect($rows)
            ->map(fn (array $row) => collect($row)->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')->implode(';'))
            ->implode("\r\n");

        return response("\xEF\xBB\xBF".$content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-migracion-comprobantes.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
        ]);

        $rows = SpreadsheetReader::rows($data['file']->getRealPath(), $data['file']->getClientOriginalExtension());
        array_shift($rows);

        abort_if(count($rows) > 500, 422, 'La plantilla admite máximo 500 registros por carga.');

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $date = trim((string) ($row[0] ?? ''));
            $thirdParty = trim((string) ($row[1] ?? ''));
            $concept = trim((string) ($row[2] ?? ''));
            $amount = (float) str_replace(',', '', (string) ($row[3] ?? 0));
            $expenseCode = trim((string) ($row[4] ?? ''));
            $payableCode = trim((string) ($row[5] ?? ''));
            $ivaRate = (float) ($row[6] ?? 0);
            $ivaCode = trim((string) ($row[7] ?? ''));
            $retentionRate = (float) ($row[8] ?? 0);
            $retentionCode = trim((string) ($row[9] ?? ''));

            if ($date === '' || $amount <= 0 || $expenseCode === '' || $payableCode === '') {
                $errors[] = "Fila {$rowNumber}: datos incompletos, se omitió.";

                continue;
            }

            try {
                $expenseAccountId = $this->findPostingAccount($expenseCode);
                $payableAccountId = $this->findPostingAccount($payableCode);
                $ivaAccountId = $ivaRate > 0 ? $this->findPostingAccount($ivaCode) : null;
                $retentionAccountId = $retentionRate > 0 ? $this->findPostingAccount($retentionCode) : null;

                $ivaAmount = round($amount * $ivaRate / 100, 2);
                $retentionAmount = round($amount * $retentionRate / 100, 2);

                $lines = collect([
                    ['chart_of_account_id' => $expenseAccountId, 'detail' => $concept, 'debit' => $amount, 'credit' => 0],
                ]);
                if ($ivaAmount > 0) {
                    $lines->push(['chart_of_account_id' => $ivaAccountId, 'detail' => 'IVA descontable', 'debit' => $ivaAmount, 'credit' => 0]);
                }
                $lines->push(['chart_of_account_id' => $payableAccountId, 'detail' => 'Cuenta por pagar '.$thirdParty, 'debit' => 0, 'credit' => round($amount + $ivaAmount - $retentionAmount, 2)]);
                if ($retentionAmount > 0) {
                    $lines->push(['chart_of_account_id' => $retentionAccountId, 'detail' => 'Retención en la fuente por pagar', 'debit' => 0, 'credit' => $retentionAmount]);
                }

                $this->accountingEntryService->post([
                    'voucher_type' => 'ajuste_contable',
                    'consecutive' => 'SERV-'.now()->format('YmdHis').'-'.$rowNumber,
                    'voucher_date' => $date,
                    'third_party' => $thirdParty,
                    'description' => 'Causación de servicio migrada: '.$concept,
                ], $lines);

                $created++;
            } catch (Throwable $e) {
                $errors[] = "Fila {$rowNumber}: ".$e->getMessage();
            }
        }

        $message = "Migración completada: {$created} comprobantes creados.";
        if ($errors !== []) {
            $message .= ' Errores: '.implode(' | ', array_slice($errors, 0, 5)).(count($errors) > 5 ? ' (y '.(count($errors) - 5).' más)' : '');
        }

        return back()->with('success', $message);
    }

    private function findPostingAccount(string $code): int
    {
        $accountId = ChartOfAccount::query()
            ->where('code', $code)
            ->where('active', true)
            ->where('allows_posting', true)
            ->value('id');

        abort_unless($accountId, 422, "La cuenta PUC {$code} no existe o no permite contabilización directa.");

        return $accountId;
    }
}
