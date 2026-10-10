<?php

namespace App\Http\Controllers;

use App\Mail\CommercialDocumentMail;
use App\Models\AccountingVoucher;
use App\Models\CommercialDocument;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BulkOperationController extends Controller
{
    private const FAMILIES = [
        'quotations' => ['label' => 'Cotizaciones', 'source' => 'commercial_document', 'document_type' => 'quotation'],
        'sales_orders' => ['label' => 'Órdenes de venta', 'source' => 'commercial_document', 'document_type' => 'sales_order'],
        'purchase_orders' => ['label' => 'Órdenes de compra', 'source' => 'commercial_document', 'document_type' => 'purchase_order'],
        'remissions' => ['label' => 'Remisiones', 'source' => 'commercial_document', 'document_type' => 'remission'],
        'customer_credit_notes' => ['label' => 'Notas crédito clientes', 'source' => 'commercial_document', 'document_type' => 'customer_credit_note'],
        'supplier_debit_notes' => ['label' => 'Notas débito proveedores', 'source' => 'commercial_document', 'document_type' => 'supplier_debit_note'],
        'sales' => ['label' => 'Facturas de venta', 'source' => 'sale'],
        'pos' => ['label' => 'Ventas POS', 'source' => 'pos_sale'],
        'purchases' => ['label' => 'Facturas de compra', 'source' => 'purchase'],
        'support_documents' => ['label' => 'Documentos soporte', 'source' => 'support_document'],
        'expense_vouchers' => ['label' => 'Egresos', 'source' => 'accounting_voucher', 'voucher_type' => 'egreso'],
        'cash_receipt_vouchers' => ['label' => 'Recibos de caja', 'source' => 'accounting_voucher', 'voucher_type' => 'recibo_caja'],
        'all_vouchers' => ['label' => 'Todos los comprobantes contables', 'source' => 'accounting_voucher'],
    ];

    public function index(Request $request): View
    {
        $family = $request->string('family')->value() ?: 'sales';
        $family = array_key_exists($family, self::FAMILIES) ? $family : 'sales';

        $documents = $this->documentsFor($family);

        return view('bulk-operations.index', [
            'families' => self::FAMILIES,
            'family' => $family,
            'documents' => $documents,
        ]);
    }

    public function print(Request $request): View
    {
        $data = $request->validate([
            'family' => ['required', 'string', Rule::in(array_keys(self::FAMILIES))],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $documents = $this->documentsFor($data['family'], $data['ids']);

        return view('bulk-operations.print', [
            'label' => self::FAMILIES[$data['family']]['label'],
            'documents' => $documents,
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'family' => ['required', 'string', Rule::in(array_keys(self::FAMILIES))],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $documents = $this->documentsFor($data['family'], $data['ids']);

        $zipPath = tempnam(sys_get_temp_dir(), 'bulk-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($documents as $document) {
            $zip->addFromString($this->fileNameFor($document).'.csv', $this->csvFor($document));
        }

        $zip->close();

        return response()->download($zipPath, 'documentos-'.now()->format('Ymd-His').'.zip')->deleteFileAfterSend();
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'family' => ['required', 'string', Rule::in(array_keys(self::FAMILIES))],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $documents = $this->documentsFor($data['family'], $data['ids']);

        $sent = 0;
        $skipped = 0;

        foreach ($documents as $document) {
            $email = $this->emailFor($document);

            if (! $email) {
                $skipped++;

                continue;
            }

            $this->sendEmailFor($document, $email);
            $sent++;
        }

        return back()->with(
            'success',
            "Envío masivo completado: {$sent} enviados, {$skipped} sin correo registrado.",
        );
    }

    /**
     * @return Collection<int, mixed>
     */
    private function documentsFor(string $family, ?array $ids = null): Collection
    {
        $configuration = self::FAMILIES[$family];

        return match ($configuration['source']) {
            'commercial_document' => CommercialDocument::query()
                ->with(['lines.item'])
                ->where('document_type', $configuration['document_type'])
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->latest('id')
                ->when(! $ids, fn ($query) => $query->limit(50))
                ->get(),
            'sale' => Sale::query()
                ->with('details.item')
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->latest('id')
                ->when(! $ids, fn ($query) => $query->limit(50))
                ->get(),
            'pos_sale' => Sale::query()
                ->with('details.item')
                ->where('invoice_number', 'like', 'POS-%')
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->latest('id')
                ->when(! $ids, fn ($query) => $query->limit(50))
                ->get(),
            'purchase' => Purchase::query()
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->latest('id')
                ->when(! $ids, fn ($query) => $query->limit(50))
                ->get(),
            'support_document' => SupportDocument::query()
                ->with('supplier')
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->latest('id')
                ->when(! $ids, fn ($query) => $query->limit(50))
                ->get(),
            'accounting_voucher' => AccountingVoucher::query()
                ->with('lines.account')
                ->when($configuration['voucher_type'] ?? null, fn ($query, $voucherType) => $query->where('voucher_type', $voucherType))
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->latest('id')
                ->when(! $ids, fn ($query) => $query->limit(50))
                ->get(),
        };
    }

    private function fileNameFor(mixed $document): string
    {
        $raw = match (true) {
            $document instanceof CommercialDocument => $document->consecutive,
            $document instanceof Sale => $document->invoice_number,
            $document instanceof Purchase => $document->invoice_number,
            $document instanceof SupportDocument => $document->consecutive,
            $document instanceof AccountingVoucher => $document->consecutive,
            default => (string) $document->id,
        };

        return preg_replace('/[^A-Za-z0-9._-]/', '-', $raw) ?: (string) $document->id;
    }

    private function emailFor(mixed $document): ?string
    {
        return match (true) {
            $document instanceof CommercialDocument => $document->recipient_email,
            $document instanceof Sale => $document->customer_email,
            $document instanceof Purchase => ThirdParty::query()
                ->where('is_supplier', true)
                ->where('name', $document->provider)
                ->value('email'),
            $document instanceof SupportDocument => $document->supplier?->email,
            $document instanceof AccountingVoucher => ThirdParty::query()
                ->where('name', $document->third_party)
                ->value('email'),
            default => null,
        };
    }

    private function sendEmailFor(mixed $document, string $email): void
    {
        if ($document instanceof CommercialDocument) {
            $label = CommercialDocument::TYPES[$document->document_type] ?? 'Documento';
            Mail::to($email)->send(new CommercialDocumentMail($document, $label));

            return;
        }

        if ($document instanceof Sale) {
            $body = "Factura: {$document->invoice_number}\nCliente: {$document->customer_name}\nTotal: {$document->total}";
            Mail::raw($body, fn ($message) => $message->to($email)->subject('Factura de venta '.$document->invoice_number));

            return;
        }

        if ($document instanceof Purchase) {
            $body = "Factura de compra: {$document->invoice_number}\nProveedor: {$document->provider}\nTotal a pagar: {$document->total_pagar}";
            Mail::raw($body, fn ($message) => $message->to($email)->subject('Factura de compra '.$document->invoice_number));

            return;
        }

        if ($document instanceof SupportDocument) {
            $body = "Documento soporte {$document->consecutive}\nConcepto: {$document->concept}\nTotal: {$document->total}";
            Mail::raw($body, fn ($message) => $message->to($email)->subject('Documento soporte '.$document->consecutive));

            return;
        }

        if ($document instanceof AccountingVoucher) {
            $voucherLabel = AccountingVoucherController::VOUCHER_TYPES[$document->voucher_type] ?? 'Comprobante contable';
            $body = "{$voucherLabel}: {$document->consecutive}\nTercero: {$document->third_party}\nTotal débito: {$document->total_debit}\nTotal crédito: {$document->total_credit}";
            Mail::raw($body, fn ($message) => $message->to($email)->subject($voucherLabel.' '.$document->consecutive));
        }
    }

    private function csvFor(mixed $document): string
    {
        $rows = match (true) {
            $document instanceof CommercialDocument => $this->csvRowsForCommercialDocument($document),
            $document instanceof Sale => $this->csvRowsForSale($document),
            $document instanceof Purchase => $this->csvRowsForPurchase($document),
            $document instanceof SupportDocument => $this->csvRowsForSupportDocument($document),
            $document instanceof AccountingVoucher => $this->csvRowsForAccountingVoucher($document),
            default => [],
        };

        $content = collect($rows)
            ->map(fn (array $row): string => collect($row)->map(fn (mixed $value): string => $this->csvCell($value))->implode(';'))
            ->implode("\r\n");

        return "\xEF\xBB\xBF".$content;
    }

    private function csvRowsForCommercialDocument(CommercialDocument $document): array
    {
        $rows = [[CommercialDocument::TYPES[$document->document_type] ?? 'Documento', $document->consecutive], ['Tercero', 'Documento', 'Cantidad', 'Valor unitario', 'Total']];

        foreach ($document->lines as $line) {
            $rows[] = [$document->third_party_name, $line->description, $line->quantity, number_format($line->unit_price, 2, '.', ''), number_format($line->line_total, 2, '.', '')];
        }

        $rows[] = ['', '', '', 'Total', number_format($document->total, 2, '.', '')];

        return $rows;
    }

    private function csvRowsForSale(Sale $sale): array
    {
        $rows = [['Factura de venta', $sale->invoice_number], ['Cliente', 'Producto', 'Cantidad', 'Valor unitario', 'Total']];

        foreach ($sale->details as $detail) {
            $rows[] = [$sale->customer_name, $detail->item->name ?? '', $detail->quantity, number_format($detail->unit_price, 2, '.', ''), number_format($detail->line_total, 2, '.', '')];
        }

        $rows[] = ['', '', '', 'Total', number_format($sale->total, 2, '.', '')];

        return $rows;
    }

    private function csvRowsForPurchase(Purchase $purchase): array
    {
        return [
            ['Factura de compra', $purchase->invoice_number],
            ['Proveedor', $purchase->provider],
            ['Subtotal', number_format($purchase->subtotal, 2, '.', '')],
            ['IVA', number_format($purchase->iva_total, 2, '.', '')],
            ['Total a pagar', number_format($purchase->total_pagar, 2, '.', '')],
        ];
    }

    private function csvRowsForSupportDocument(SupportDocument $document): array
    {
        return [
            ['Documento soporte', $document->consecutive],
            ['Proveedor', $document->supplier->name ?? ''],
            ['Concepto', $document->concept],
            ['Subtotal', number_format($document->subtotal, 2, '.', '')],
            ['Total', number_format($document->total, 2, '.', '')],
        ];
    }

    private function csvRowsForAccountingVoucher(AccountingVoucher $document): array
    {
        $voucherLabel = AccountingVoucherController::VOUCHER_TYPES[$document->voucher_type] ?? 'Comprobante contable';
        $rows = [[$voucherLabel, $document->consecutive], ['Tercero', $document->third_party], ['Cuenta', 'Detalle', 'Débito', 'Crédito']];

        foreach ($document->lines as $line) {
            $rows[] = [
                ($line->account->code ?? '').' '.($line->account->name ?? ''),
                $line->detail,
                number_format($line->debit, 2, '.', ''),
                number_format($line->credit, 2, '.', ''),
            ];
        }

        $rows[] = ['', 'Totales', number_format($document->total_debit, 2, '.', ''), number_format($document->total_credit, 2, '.', '')];

        return $rows;
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")
            ? '"'.str_replace('"', '""', $value).'"'
            : $value;
    }
}
