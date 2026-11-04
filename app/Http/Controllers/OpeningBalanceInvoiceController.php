<?php

namespace App\Http\Controllers;

use App\Models\OpeningBalanceInvoice;
use App\Services\OpeningBalanceImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OpeningBalanceInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['cliente', 'proveedor'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $invoices = OpeningBalanceInvoice::query()
            ->withSum('payments as paid_total', 'amount')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('invoice_type', $type))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('third_party_name', 'like', "%{$search}%")
                        ->orWhere('third_party_document', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhere('cufe', 'like', "%{$search}%");
                });
            })
            ->orderBy('due_date')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('accounting.opening-balances.index', [
            'invoices' => $invoices,
            'filters' => $filters,
        ]);
    }

    public function template(): Response
    {
        $rows = [
            ['Tipo cartera', 'Documento tercero', 'Factura', 'Fecha factura', 'Fecha vencimiento', 'CUFE', 'Cuenta PUC', 'Débito', 'Crédito', 'Detalle'],
            ['cliente', 'NIT o cédula del cliente', 'FV-001', '2026-01-15', '2026-02-15', '', '1305', '250000', '0', 'Saldo pendiente cliente'],
            ['proveedor', 'NIT del proveedor', 'FC-001', '2026-01-10', '2026-02-10', '', '2205', '0', '150000', 'Saldo pendiente proveedor'],
            ['', '', '', '', '', '', '3105', '0', '100000', 'Contrapartida de apertura'],
        ];
        $content = collect($rows)
            ->map(fn (array $row) => collect($row)->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')->implode(';'))
            ->implode("\r\n");

        return response("\xEF\xBB\xBF".$content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-saldos-iniciales.csv"',
        ]);
    }

    public function import(Request $request, OpeningBalanceImportService $importService): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
            'xml_files' => ['nullable', 'array', 'max:500'],
            'xml_files.*' => ['file', 'mimes:xml', 'max:4096'],
            'voucher_date' => ['required', 'date'],
        ]);

        $voucher = $importService->import(
            $data['file'],
            $data['xml_files'] ?? [],
            $data['voucher_date'],
            AccountingVoucherController::nextInternalConsecutive('saldos_iniciales'),
        );

        return redirect()
            ->route('accounting.vouchers.accounting', $voucher)
            ->with('success', 'Se creó el comprobante de saldos iniciales y la cartera quedó lista para registrar abonos.');
    }

    public function storePayment(Request $request, OpeningBalanceInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($data, $invoice): RedirectResponse {
            $lockedInvoice = OpeningBalanceInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);
            $paidAmount = (float) $lockedInvoice->payments()->sum('amount');
            $balance = round((float) $lockedInvoice->opening_balance - $paidAmount, 2);
            $amount = round((float) $data['amount'], 2);

            if ($amount > $balance) {
                return back()->withErrors([
                    'amount' => 'El abono no puede superar el saldo pendiente ($'.number_format($balance, 2).').',
                ])->withInput();
            }

            $lockedInvoice->payments()->create([
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            return redirect()
                ->route('accounting.opening-balances.index', ['type' => $lockedInvoice->invoice_type])
                ->with('success', 'Abono registrado correctamente.');
        });
    }
}
