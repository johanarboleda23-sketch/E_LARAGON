<?php

namespace App\Http\Controllers;

use App\Models\AccountingVoucherLine;
use App\Models\CommercialDocument;
use App\Models\PaymentMethod;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function data(Request $request): array
    {
        $filters = validator([
            'from' => $request->query('from', now()->startOfMonth()->toDateString()),
            'to' => $request->query('to', now()->toDateString()),
            'type' => $request->query('type', 'all'),
            'account_from' => $request->query('account_from'),
            'account_to' => $request->query('account_to'),
            'third_party' => $request->query('third_party'),
        ], [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['required', 'in:all,purchases,sales,support,payroll,accounting,taxes,commercial'],
            'account_from' => ['nullable', 'string', 'max:20'],
            'account_to' => ['nullable', 'string', 'max:20', 'required_with:account_from', 'gte:account_from'],
            'third_party' => ['nullable', 'string', 'max:255'],
        ], [
            'from.required' => 'Indica la fecha inicial.',
            'from.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'to.required' => 'Indica la fecha final.',
            'to.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'type.required' => 'Selecciona un módulo.',
            'type.in' => 'El módulo seleccionado no es válido.',
            'account_to.required_with' => 'Indica la cuenta PUC final.',
            'account_to.gte' => 'La cuenta PUC final debe ser igual o posterior a la inicial.',
        ])->validate();

        $from = $filters['from'];
        $to = $filters['to'];
        $type = $filters['type'];
        $accountFrom = $filters['account_from'] ?? null;
        $accountTo = $filters['account_to'] ?? null;
        $thirdParty = $filters['third_party'] ?? null;
        $rows = collect();

        if (in_array($type, ['all', 'purchases'], true)) {
            Purchase::whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)->get()->each(fn ($item) => $rows->push(['date' => $item->purchase_date->toDateString(), 'type' => 'Compra', 'document' => $item->invoice_number, 'third_party' => $item->provider, 'total' => $item->total_pagar]));
        }
        if (in_array($type, ['all', 'sales'], true)) {
            Sale::whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)->get()->each(fn ($item) => $rows->push(['date' => $item->sale_date->toDateString(), 'type' => 'Venta', 'document' => $item->invoice_number, 'third_party' => $item->customer_name, 'total' => $item->total]));
        }
        if (in_array($type, ['all', 'support'], true)) {
            SupportDocument::whereDate('document_date', '>=', $from)->whereDate('document_date', '<=', $to)->with('supplier')->get()->each(fn ($item) => $rows->push(['date' => $item->document_date->toDateString(), 'type' => 'Documento soporte', 'document' => $item->consecutive, 'third_party' => $item->supplier->name, 'total' => $item->total]));
        }
        if (in_array($type, ['all', 'payroll'], true)) {
            PayrollRun::whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->get()->each(fn ($item) => $rows->push(['date' => $item->payment_date->toDateString(), 'type' => 'Nómina', 'document' => $item->period, 'third_party' => 'Empleados', 'total' => $item->total_net]));
        }
        if (in_array($type, ['all', 'commercial'], true)) {
            CommercialDocument::whereDate('document_date', '>=', $from)->whereDate('document_date', '<=', $to)->get()->each(fn ($item) => $rows->push([
                'date' => $item->document_date->toDateString(),
                'type' => CommercialDocument::TYPES[$item->document_type] ?? $item->document_type,
                'document' => $item->consecutive,
                'third_party' => $item->third_party_name,
                'total' => $item->total,
            ]));
        }
        if ($type === 'accounting') {
            AccountingVoucherLine::whereHas('voucher', function ($query) use ($from, $to, $thirdParty) {
                $query->whereDate('voucher_date', '>=', $from)->whereDate('voucher_date', '<=', $to);
                if ($thirdParty) {
                    $query->where('third_party', 'like', '%'.$thirdParty.'%');
                }
            })
                ->when($accountFrom && $accountTo, fn ($query) => $query->whereHas('account', fn ($accountQuery) => $accountQuery->whereBetween('code', [$accountFrom, $accountTo])))
                ->with(['voucher', 'account'])
                ->get()
                ->each(fn ($line) => $rows->push([
                    'date' => $line->voucher->voucher_date->toDateString(),
                    'type' => $line->account->code.' - '.$line->account->name,
                    'document' => $line->voucher->consecutive,
                    'third_party' => $line->voucher->third_party ?? $line->detail,
                    'total' => $line->debit - $line->credit,
                ]));
        }
        if ($type === 'taxes') {
            Purchase::whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)->get()->each(function ($item) use (&$rows) {
                if ($item->iva_total > 0) {
                    $rows->push(['date' => $item->purchase_date->toDateString(), 'type' => 'IVA descontable (compra)', 'document' => $item->invoice_number, 'third_party' => $item->provider, 'total' => $item->iva_total]);
                }
                if ($item->retefuente > 0) {
                    $rows->push(['date' => $item->purchase_date->toDateString(), 'type' => 'Retención en la fuente practicada', 'document' => $item->invoice_number, 'third_party' => $item->provider, 'total' => $item->retefuente]);
                }
            });
            Sale::whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)->get()->each(function ($item) use (&$rows) {
                if ($item->iva_total > 0) {
                    $rows->push(['date' => $item->sale_date->toDateString(), 'type' => 'IVA generado (venta)', 'document' => $item->invoice_number, 'third_party' => $item->customer_name, 'total' => $item->iva_total]);
                }
            });
        }

        return compact('from', 'to', 'type', 'rows', 'accountFrom', 'accountTo', 'thirdParty');
    }

    public function index(Request $request)
    {
        $report = $this->data($request);
        $report['cashAndBanks'] = $this->cashAndBankBalances();

        return view('reports.index', $report);
    }

    /**
     * Calcula el saldo contable (débitos - créditos) de cada forma de pago con cuenta PUC asociada,
     * para mostrarlo en el panel flotante de Caja/Bancos.
     *
     * @return array<int, array{name: string, bank_name: ?string, account_number: ?string, is_cash: bool, balance: float}>
     */
    private function cashAndBankBalances(): array
    {
        return PaymentMethod::query()
            ->whereNotNull('chart_of_account_id')
            ->with('account')
            ->orderByDesc('is_cash')
            ->orderBy('name')
            ->get()
            ->map(function ($paymentMethod) {
                $balance = (float) AccountingVoucherLine::query()
                    ->where('chart_of_account_id', $paymentMethod->chart_of_account_id)
                    ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
                    ->value('balance');

                return [
                    'name' => $paymentMethod->name,
                    'bank_name' => $paymentMethod->bank_name,
                    'account_number' => $paymentMethod->account_number,
                    'is_cash' => $paymentMethod->is_cash,
                    'balance' => $balance,
                ];
            })
            ->values()
            ->all();
    }

    public function excel(Request $request)
    {
        $report = $this->data($request);
        $lines = [['Fecha', 'Tipo', 'Documento', 'Tercero', 'Total']];
        foreach ($report['rows'] as $row) {
            $lines[] = [$row['date'], $row['type'], $row['document'], $row['third_party'], number_format($row['total'], 2, '.', '')];
        }
        $content = collect($lines)->map(fn ($line) => collect($line)->map(fn ($value) => '"'.str_replace('"', '""', $value).'"')->implode(';'))->implode("\r\n");

        return response("\xEF\xBB\xBF".$content, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="reporte-'.$report['from'].'-'.$report['to'].'.csv"']);
    }

    public function pdf(Request $request)
    {
        return view('reports.print', $this->data($request));
    }
}
