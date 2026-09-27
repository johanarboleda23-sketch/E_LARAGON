<?php

namespace App\Http\Controllers;

use App\Mail\CommercialDocumentMail;
use App\Models\CommercialDocument;
use App\Models\CommercialDocumentLine;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\ThirdParty;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommercialDocumentController extends Controller
{
    private const CONFIGURATION = [
        'quotation' => [
            'label' => 'Cotización',
            'prefix' => 'COT',
            'party' => 'customer',
            'partyLabel' => 'Cliente',
            'indexRoute' => 'commercial-documents.quotations',
            'storeRoute' => 'commercial-documents.quotations.store',
            'saveLabel' => 'Guardar cotización',
            'convertLabel' => 'Facturar',
            'editConsecutive' => true,
        ],
        'sales_order' => [
            'label' => 'Orden de venta',
            'prefix' => 'OV',
            'party' => 'customer',
            'partyLabel' => 'Cliente',
            'indexRoute' => 'commercial-documents.sales-orders',
            'storeRoute' => 'commercial-documents.sales-orders.store',
            'saveLabel' => 'Guardar orden',
            'convertLabel' => 'Facturar',
            'editConsecutive' => false,
        ],
        'remission' => [
            'label' => 'Remisión',
            'prefix' => 'REM',
            'party' => 'customer',
            'partyLabel' => 'Cliente',
            'indexRoute' => 'commercial-documents.remissions',
            'storeRoute' => 'commercial-documents.remissions.store',
            'saveLabel' => 'Guardar remisión',
            'convertLabel' => 'Facturar',
            'editConsecutive' => true,
        ],
        'purchase_order' => [
            'label' => 'Orden de compra',
            'prefix' => 'OC',
            'party' => 'supplier',
            'partyLabel' => 'Proveedor',
            'indexRoute' => 'commercial-documents.purchase-orders',
            'storeRoute' => 'commercial-documents.purchase-orders.store',
            'saveLabel' => 'Guardar orden',
            'convertLabel' => 'Registrar compra',
            'editConsecutive' => true,
        ],
        'customer_credit_note' => [
            'label' => 'Nota crédito clientes',
            'prefix' => 'NC',
            'party' => 'customer',
            'partyLabel' => 'Cliente',
            'indexRoute' => 'commercial-documents.customer-credit-notes',
            'storeRoute' => 'commercial-documents.customer-credit-notes.store',
            'saveLabel' => 'Guardar nota crédito',
            'referenceType' => 'sale',
            'editConsecutive' => false,
        ],
        'supplier_debit_note' => [
            'label' => 'Nota débito proveedores',
            'prefix' => 'ND',
            'party' => 'supplier',
            'partyLabel' => 'Proveedor',
            'indexRoute' => 'commercial-documents.supplier-debit-notes',
            'storeRoute' => 'commercial-documents.supplier-debit-notes.store',
            'saveLabel' => 'Guardar nota débito',
            'referenceType' => 'purchase',
            'editConsecutive' => false,
        ],
    ];

    public function index(Request $request): View
    {
        $type = $this->documentType($request);
        $configuration = self::CONFIGURATION[$type];
        $isNote = isset($configuration['referenceType']);
        $isPurchaseDocument = $configuration['party'] === 'supplier';
        $currentDocument = null;

        if ($request->filled('document')) {
            $currentDocument = CommercialDocument::query()
                ->with(['lines', 'referenceSale', 'referencePurchase'])
                ->where('document_type', $type)
                ->findOrFail($request->integer('document'));

            abort_if($currentDocument->status !== 'draft', 409);
        }

        $partyFlag = $configuration['party'] === 'supplier' ? 'is_supplier' : 'is_customer';
        $thirdParties = ThirdParty::query()
            ->where('active', true)
            ->where($partyFlag, true)
            ->orderBy('name')
            ->get(['id', 'name', 'document', 'email']);

        $items = Item::query()
            ->when($configuration['party'] !== 'supplier', fn ($query) => $query->where('type', 'producto'))
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'sale_price', 'purchase_price', 'stock']);

        $referenceInvoices = collect();
        if ($type === 'customer_credit_note') {
            $referenceInvoices = Sale::query()
                ->latest('id')
                ->limit(100)
                ->get(['id', 'invoice_number', 'sale_date', 'customer_name', 'customer_document', 'total']);
        } elseif ($type === 'supplier_debit_note') {
            $referenceInvoices = Purchase::query()
                ->latest('id')
                ->limit(100)
                ->get(['id', 'invoice_number', 'purchase_date', 'provider', 'total_pagar']);
        }

        $documents = CommercialDocument::query()
            ->with(['lines.item', 'referenceSale:id,invoice_number', 'referencePurchase:id,invoice_number', 'accountingVoucher'])
            ->where('document_type', $type)
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $defaultConsecutive = $configuration['prefix'].'-'.str_pad(
            (string) (CommercialDocument::query()->where('document_type', $type)->count() + 1),
            6,
            '0',
            STR_PAD_LEFT,
        );
        $formLines = old('items', $currentDocument?->lines->map(fn (CommercialDocumentLine $line): array => [
            'item_id' => $line->item_id,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'iva_percentage' => $line->iva_percentage,
            'discount_percentage' => $line->discount_percentage,
            'utility_percentage' => $line->utility_percentage,
        ])->all() ?? [[
            'item_id' => '',
            'quantity' => 1,
            'unit_price' => '',
            'iva_percentage' => 19,
            'discount_percentage' => 0,
            'utility_percentage' => 0,
        ]]);

        return view('commercial-documents.index', compact(
            'type',
            'configuration',
            'currentDocument',
            'defaultConsecutive',
            'documents',
            'formLines',
            'items',
            'isNote',
            'isPurchaseDocument',
            'referenceInvoices',
            'thirdParties',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->documentType($request);
        $configuration = self::CONFIGURATION[$type];
        $currentDocument = null;

        if ($request->filled('document_id')) {
            $currentDocument = CommercialDocument::query()
                ->where('document_type', $type)
                ->findOrFail($request->integer('document_id'));
            abort_if($currentDocument->status !== 'draft', 409);
        }

        $uniqueConsecutive = Rule::unique('commercial_documents', 'consecutive')
            ->where('company_id', (int) session('company_id'))
            ->where('document_type', $type)
            ->ignore($currentDocument?->id);

        $data = $request->validate([
            'document_id' => ['nullable', 'integer'],
            'consecutive' => ['required', 'string', 'max:60', $uniqueConsecutive],
            'third_party_id' => ['required', 'integer', 'exists:third_parties,id'],
            'reference_invoice_id' => [Rule::requiredIf(isset($configuration['referenceType'])), 'nullable', 'integer'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'document_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.iva_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_percentage' => [$type === 'purchase_order' ? 'nullable' : 'required', 'numeric', 'min:0', 'max:100'],
            'items.*.utility_percentage' => [$type === 'purchase_order' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $partyFlag = $configuration['party'] === 'supplier' ? 'is_supplier' : 'is_customer';
        $thirdParty = ThirdParty::query()
            ->where('active', true)
            ->where($partyFlag, true)
            ->findOrFail($data['third_party_id']);

        $referenceSale = null;
        $referencePurchase = null;
        if ($type === 'customer_credit_note') {
            $referenceSale = Sale::query()
                ->where(function ($query) use ($thirdParty): void {
                    if ($thirdParty->document) {
                        $query->where('customer_document', $thirdParty->document);
                    } else {
                        $query->where('customer_name', $thirdParty->name);
                    }
                })
                ->findOrFail($data['reference_invoice_id']);
        } elseif ($type === 'supplier_debit_note') {
            $referencePurchase = Purchase::query()
                ->where('provider', $thirdParty->name)
                ->findOrFail($data['reference_invoice_id']);
        }

        $preparedLines = [];
        $subtotal = 0;
        $discountTotal = 0;
        $ivaTotal = 0;

        foreach ($data['items'] as $lineData) {
            $item = Item::query()->findOrFail($lineData['item_id']);
            $quantity = (int) $lineData['quantity'];
            $unitPrice = (float) $lineData['unit_price'];
            $ivaPercentage = (float) $lineData['iva_percentage'];
            $discountPercentage = $type === 'purchase_order' ? 0 : (float) ($lineData['discount_percentage'] ?? 0);
            $utilityPercentage = $type === 'purchase_order' ? (float) $lineData['utility_percentage'] : 0;
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $discountAmount = round($lineSubtotal * $discountPercentage / 100, 2);
            $ivaAmount = round(($lineSubtotal - $discountAmount) * $ivaPercentage / 100, 2);
            $lineTotal = round($lineSubtotal - $discountAmount + $ivaAmount, 2);

            $preparedLines[] = [
                'item_id' => $item->id,
                'description' => $item->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'iva_percentage' => $ivaPercentage,
                'discount_percentage' => $discountPercentage,
                'utility_percentage' => $utilityPercentage,
                'line_subtotal' => $lineSubtotal,
                'discount_amount' => $discountAmount,
                'iva_amount' => $ivaAmount,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineSubtotal;
            $discountTotal += $discountAmount;
            $ivaTotal += $ivaAmount;
        }

        $document = DB::transaction(function () use ($configuration, $currentDocument, $data, $preparedLines, $subtotal, $discountTotal, $ivaTotal, $thirdParty, $type, $referenceSale, $referencePurchase): CommercialDocument {
            $document = $currentDocument
                ? CommercialDocument::query()->lockForUpdate()->findOrFail($currentDocument->id)
                : new CommercialDocument;

            abort_if($document->exists && $document->status !== 'draft', 409);

            $document->fill([
                'company_id' => (int) session('company_id'),
                'created_by' => $document->created_by ?? auth()->id(),
                'document_type' => $type,
                'consecutive' => $configuration['editConsecutive'] || ! $document->exists
                    ? $data['consecutive']
                    : $document->consecutive,
                'status' => 'draft',
                'third_party_id' => $thirdParty->id,
                'third_party_name' => $thirdParty->name,
                'third_party_document' => $thirdParty->document,
                'reference_sale_id' => $referenceSale?->id,
                'reference_purchase_id' => $referencePurchase?->id,
                'recipient_email' => $data['recipient_email'] ?? $thirdParty->email,
                'document_date' => $data['document_date'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'iva_total' => round($ivaTotal, 2),
                'total' => round($subtotal - $discountTotal + $ivaTotal, 2),
            ]);
            $document->save();
            $document->lines()->delete();
            $document->lines()->createMany($preparedLines);

            return $document;
        });

        return redirect()
            ->route($configuration['indexRoute'], ['document' => $document->id])
            ->with('success', $configuration['label'].' guardada.');
    }

    public function updateConsecutive(Request $request, CommercialDocument $document): RedirectResponse
    {
        $configuration = self::CONFIGURATION[$document->document_type] ?? abort(404);
        abort_unless($configuration['editConsecutive'], 403);
        abort_if($document->status !== 'draft', 409);

        $data = $request->validate([
            'consecutive' => [
                'required',
                'string',
                'max:60',
                Rule::unique('commercial_documents', 'consecutive')
                    ->where('company_id', $document->company_id)
                    ->where('document_type', $document->document_type)
                    ->ignore($document->id),
            ],
        ]);

        $document->update(['consecutive' => $data['consecutive']]);

        return redirect()
            ->route($configuration['indexRoute'])
            ->with('success', 'Consecutivo actualizado.');
    }

    public function email(Request $request, CommercialDocument $document): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $document->load(['lines.item', 'referenceSale', 'referencePurchase']);
        $configuration = self::CONFIGURATION[$document->document_type] ?? abort(404);

        Mail::to($data['email'])->send(new CommercialDocumentMail($document, $configuration['label']));

        return back()->with('success', 'Documento enviado por correo.');
    }

    public function convert(CommercialDocument $document): RedirectResponse
    {
        abort_unless(in_array($document->document_type, ['quotation', 'sales_order', 'remission', 'purchase_order'], true), 422);
        $configuration = self::CONFIGURATION[$document->document_type] ?? abort(404);
        abort_if($document->status !== 'draft', 409);

        $document = DB::transaction(function () use ($document): CommercialDocument {
            $document = CommercialDocument::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($document->id);

            abort_if($document->status !== 'draft', 409);

            if ($document->document_type === 'purchase_order') {
                $purchase = $this->createPurchaseFromDocument($document);
                $document->update([
                    'status' => 'converted',
                    'converted_purchase_id' => $purchase->id,
                    'converted_at' => now(),
                ]);
            } else {
                $sale = $this->createSaleFromDocument($document);
                $document->update([
                    'status' => 'converted',
                    'converted_sale_id' => $sale->id,
                    'converted_at' => now(),
                ]);
            }

            return $document;
        });

        return redirect()
            ->route($configuration['indexRoute'])
            ->with('success', $configuration['label'].' convertido correctamente.');
    }

    public function sendToDian(CommercialDocument $document): RedirectResponse
    {
        abort_unless(in_array($document->document_type, ['customer_credit_note', 'supplier_debit_note'], true), 422);

        return back()->with('error', 'La nota no se envió: la integración con la DIAN no está configurada.');
    }

    public function statement(CommercialDocument $document): View
    {
        abort_unless(in_array($document->document_type, ['customer_credit_note', 'supplier_debit_note'], true), 422);

        $isCustomer = $document->document_type === 'customer_credit_note';
        $referenceInvoice = $isCustomer
            ? $document->referenceSale()->firstOrFail()
            : $document->referencePurchase()->firstOrFail();
        $relatedNotes = CommercialDocument::query()
            ->with('accountingVoucher')
            ->where('document_type', $document->document_type)
            ->where($isCustomer ? 'reference_sale_id' : 'reference_purchase_id', $referenceInvoice->id)
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        $transactions = collect([[
            'date' => $isCustomer ? $referenceInvoice->sale_date : $referenceInvoice->purchase_date,
            'document' => $referenceInvoice->invoice_number,
            'description' => $isCustomer ? 'Factura de venta' : 'Factura de proveedor',
            'debit' => $isCustomer ? (float) ($referenceInvoice->total ?? 0) : 0,
            'credit' => $isCustomer ? 0 : (float) ($referenceInvoice->total_pagar ?? 0),
            'status' => 'Registrada',
        ]]);

        foreach ($relatedNotes as $relatedNote) {
            $transactions->push([
                'date' => $relatedNote->document_date,
                'document' => $relatedNote->consecutive,
                'description' => CommercialDocument::TYPES[$relatedNote->document_type],
                'debit' => $isCustomer ? 0 : (float) $relatedNote->total,
                'credit' => $isCustomer ? (float) $relatedNote->total : 0,
                'status' => $relatedNote->accountingVoucher ? 'Contabilizada' : 'Pendiente de contabilizar',
            ]);
        }

        $balance = $isCustomer
            ? $transactions->sum('debit') - $transactions->sum('credit')
            : $transactions->sum('credit') - $transactions->sum('debit');
        $partyLabel = $isCustomer ? 'Cliente' : 'Proveedor';

        return view('commercial-documents.statement', compact(
            'balance',
            'document',
            'partyLabel',
            'referenceInvoice',
            'transactions',
        ));
    }

    public function print(CommercialDocument $document): View
    {
        $document->load(['lines.item', 'referenceSale', 'referencePurchase']);

        return view('commercial-documents.print', [
            'document' => $document,
            'label' => self::CONFIGURATION[$document->document_type]['label'] ?? abort(404),
        ]);
    }

    public function download(CommercialDocument $document): Response
    {
        $document->load(['lines.item', 'referenceSale', 'referencePurchase']);
        $label = self::CONFIGURATION[$document->document_type]['label'] ?? abort(404);
        $rows = [[$label, $document->consecutive], ['Fecha', 'Tercero', 'Documento', 'Cantidad', 'Valor unitario', 'Total']];
        $referenceInvoiceNumber = $document->referenceSale?->invoice_number ?? $document->referencePurchase?->invoice_number;
        if ($referenceInvoiceNumber) {
            $rows[] = ['Factura origen', $referenceInvoiceNumber];
        }

        foreach ($document->lines as $line) {
            $rows[] = [
                $document->document_date->toDateString(),
                $document->third_party_name,
                $line->description,
                $line->quantity,
                number_format($line->unit_price, 2, '.', ''),
                number_format($line->line_total, 2, '.', ''),
            ];
        }

        $rows[] = ['', '', '', '', 'Subtotal', number_format($document->subtotal, 2, '.', '')];
        $rows[] = ['', '', '', '', 'Descuentos', number_format($document->discount_total, 2, '.', '')];
        $rows[] = ['', '', '', '', 'IVA', number_format($document->iva_total, 2, '.', '')];
        $rows[] = ['', '', '', '', 'Total', number_format($document->total, 2, '.', '')];
        $content = collect($rows)
            ->map(fn (array $row): string => collect($row)->map(fn (mixed $value): string => $this->csvCell($value))->implode(';'))
            ->implode("\r\n");
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $document->consecutive);

        return response("\xEF\xBB\xBF".$content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    private function createSaleFromDocument(CommercialDocument $document): Sale
    {
        $sale = Sale::create([
            'invoice_number' => $document->consecutive,
            'customer_name' => $document->third_party_name,
            'customer_document' => $document->third_party_document,
            'customer_email' => $document->recipient_email,
            'sale_date' => $document->document_date,
            'subtotal' => $document->subtotal,
            'iva_total' => $document->iva_total,
            'discount_total' => $document->discount_total,
            'withholding_concept' => 'none',
            'retention_base' => 0,
            'retention_total' => 0,
            'total' => $document->total,
            'created_by' => auth()->id(),
        ]);

        foreach ($document->lines as $line) {
            $item = Item::query()->lockForUpdate()->findOrFail($line->item_id);

            if ($item->type === 'producto' && $item->stock < $line->quantity) {
                throw ValidationException::withMessages([
                    'items' => "Stock insuficiente para {$item->name}.",
                ]);
            }

            SaleDetail::create([
                'sale_id' => $sale->id,
                'item_id' => $item->id,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'iva_percentage' => $line->iva_percentage,
                'discount_percentage' => $line->discount_percentage,
                'line_total' => round($line->line_subtotal - $line->discount_amount, 2),
            ]);

            if ($item->type === 'producto') {
                $stockBefore = $item->stock;
                $item->decrement('stock', $line->quantity);
                $item->refresh();
                InventoryMovement::create([
                    'item_id' => $item->id,
                    'type' => 'salida',
                    'quantity' => $line->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $item->stock,
                    'reason' => 'Factura '.$sale->invoice_number,
                ]);
            }
        }

        return $sale;
    }

    private function createPurchaseFromDocument(CommercialDocument $document): Purchase
    {
        $purchase = Purchase::create([
            'invoice_number' => $document->consecutive,
            'provider' => $document->third_party_name,
            'purchase_date' => $document->document_date,
            'subtotal' => $document->subtotal,
            'iva_total' => $document->iva_total,
            'retefuente' => 0,
            'total_pagar' => $document->total,
            'created_by' => auth()->id(),
        ]);

        foreach ($document->lines as $line) {
            $item = Item::query()->lockForUpdate()->findOrFail($line->item_id);
            $calculatedSalePrice = $line->unit_price * (1 + $line->utility_percentage / 100);

            PurchaseDetail::create([
                'purchase_id' => $purchase->id,
                'item_id' => $item->id,
                'quantity' => $line->quantity,
                'cost_price' => $line->unit_price,
                'iva_percentage' => $line->iva_percentage,
                'iva_value' => $line->unit_price * $line->iva_percentage / 100,
                'utility_percentage' => $line->utility_percentage,
                'calculated_sale_price' => $calculatedSalePrice,
            ]);

            if ($item->type === 'producto') {
                $stockBefore = $item->stock;
                $item->increment('stock', $line->quantity);
                $item->refresh();
                $item->update([
                    'purchase_price' => $line->unit_price,
                    'sale_price' => $calculatedSalePrice,
                ]);
                InventoryMovement::create([
                    'item_id' => $item->id,
                    'purchase_id' => $purchase->id,
                    'type' => 'entrada',
                    'quantity' => $line->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $item->stock,
                    'reason' => 'Compra '.$purchase->invoice_number,
                ]);
            }
        }

        return $purchase;
    }

    private function documentType(Request $request): string
    {
        $type = $request->route('documentType');
        abort_unless(is_string($type) && isset(self::CONFIGURATION[$type]), 404);

        return $type;
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        if (preg_match('/^[=+\-@]/u', $value)) {
            $value = "'".$value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}
