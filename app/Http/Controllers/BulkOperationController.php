<?php

namespace App\Http\Controllers;

use App\Mail\CommercialDocumentMail;
use App\Models\CommercialDocument;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use ZipArchive;

class BulkOperationController extends Controller
{
    private const FAMILIES = [
        'quotations' => ['label' => 'Cotizaciones', 'source' => 'commercial_document', 'document_type' => 'quotation'],
        'sales_orders' => ['label' => 'Órdenes de venta', 'source' => 'commercial_document', 'document_type' => 'sales_order'],
        'sales' => ['label' => 'Facturas de venta', 'source' => 'sale'],
        'purchases' => ['label' => 'Facturas de compra', 'source' => 'purchase'],
        'support_documents' => ['label' => 'Documentos soporte', 'source' => 'support_document'],
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

    public function download(Request $request): Response
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
        };
    }

    private function fileNameFor(mixed $document): string
    {
        $raw = match (true) {
            $document instanceof CommercialDocument => $document->consecutive,
            $document instanceof Sale => $document->invoice_number,
            $document instanceof Purchase => $document->invoice_number,
            $document instanceof SupportDocument => $document->consecutive,
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
        }
    }

    private function csvFor(mixed $document): string
    {
        $rows = match (true) {
            $document instanceof CommercialDocument => $this->csvRowsForCommercialDocument($document),
            $document instanceof Sale => $this->csvRowsForSale($document),
            $document instanceof Purchase => $this->csvRowsForPurchase($document),
            $document instanceof SupportDocument => $this->csvRowsForSupportDocument($document),
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

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")
            ? '"'.str_replace('"', '""', $value).'"'
            : $value;
    }
}
