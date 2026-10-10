<?php

namespace App\Http\Controllers;

use App\Models\NumberingResolution;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
use App\Services\AccountingEntryService;
use App\Services\FactusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SupportDocumentController extends Controller
{
    public function __construct(
        private readonly AccountingEntryService $accountingEntryService,
        private readonly FactusService $factusService,
    ) {}

    public function index()
    {
        $suppliers = ThirdParty::where('is_supplier', true)->where('active', true)->orderBy('name')->get();
        $documents = SupportDocument::with(['supplier', 'creator'])->latest()->take(20)->get();
        $withholdings = config('colombia_withholdings');
        $nextConsecutive = NumberingResolution::peekNext('support_document');

        return view('support-documents.index', compact('suppliers', 'documents', 'withholdings', 'nextConsecutive'));
    }

    public function show(SupportDocument $document)
    {
        $document->load('supplier');

        return view('support-documents.show', compact('document'));
    }

    public function edit(SupportDocument $document)
    {
        $suppliers = ThirdParty::where('is_supplier', true)->where('active', true)->orderBy('name')->get();
        $withholdings = config('colombia_withholdings');

        return view('support-documents.edit', compact('document', 'suppliers', 'withholdings'));
    }

    public function update(Request $request, SupportDocument $document)
    {
        $data = $request->validate([
            'consecutive' => ['required', 'string', 'max:60', Rule::unique('support_documents', 'consecutive')->ignore($document->id)],
            'document_date' => 'required|date',
            'third_party_id' => 'required|exists:third_parties,id',
            'concept' => 'required|string|max:255',
            'subtotal' => 'required|numeric|min:0',
            'iva_total' => 'required|numeric|min:0',
            'withholding_concept' => 'required|string|in:'.implode(',', array_keys(config('colombia_withholdings.concepts'))),
        ]);

        $concept = config('colombia_withholdings.concepts.'.$data['withholding_concept']);
        $base = $concept['base_on'] === 'iva' ? $data['iva_total'] : $data['subtotal'];
        $minimum = $concept['base_uvt'] * config('colombia_withholdings.uvt');
        $retention = $base >= $minimum ? round($base * $concept['rate'], 2) : 0;
        $data['retention_total'] = $retention;
        $data['total'] = round($data['subtotal'] + $data['iva_total'] - $retention, 2);
        $data['skip_dian'] = $request->boolean('skip_dian');

        // Si ya estaba contabilizado, se revierte el asiento anterior para volver a generarlo con
        // los valores corregidos (igual que al editar una compra o una venta).
        if ($document->accounting_voucher_id) {
            $oldVoucher = $document->accountingVoucher;
            $document->update(['accounting_voucher_id' => null]);
            $oldVoucher?->lines()->delete();
            $oldVoucher?->delete();
        }

        $document->update($data);

        $skipReason = null;
        $accountingVoucher = $this->accountingEntryService->postSupportDocument($document->fresh(), $skipReason);

        return redirect()->route('support-documents.show', $document)->with('success', $accountingVoucher
            ? '¡Documento actualizado y vuelto a contabilizar correctamente!'
            : '¡Documento actualizado! No se contabilizó automáticamente: '.($skipReason ?? 'configura las cuentas PUC necesarias.'));
    }

    public function statement(SupportDocument $document)
    {
        $documents = SupportDocument::query()
            ->with(['supplier', 'payments'])
            ->where('third_party_id', $document->third_party_id)
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        $balance = $documents->sum(fn (SupportDocument $item) => $item->balanceDue((float) $item->total));

        return view('support-documents.statement', [
            'supplier' => $document->supplier,
            'documents' => $documents,
            'balance' => $balance,
        ]);
    }

    public function account(SupportDocument $document)
    {
        if ($document->accounting_voucher_id) {
            return back()->with('success', 'Este documento ya estaba contabilizado.');
        }

        $skipReason = null;
        $voucher = $this->accountingEntryService->postSupportDocument($document, $skipReason);

        return back()->with('success', $voucher
            ? '¡Documento contabilizado correctamente!'
            : 'No se pudo contabilizar: '.($skipReason ?? 'configura las cuentas PUC necesarias.'));
    }

    public function store(Request $request)
    {
        $hasActiveResolution = NumberingResolution::query()->where('document_type', 'support_document')->where('active', true)->exists();

        $data = $request->validate([
            'consecutive' => [$hasActiveResolution ? 'nullable' : 'required', 'string', 'max:60', 'unique:support_documents,consecutive'],
            'document_date' => 'required|date',
            'third_party_id' => 'required|exists:third_parties,id',
            'concept' => 'required|string|max:255',
            'subtotal' => 'required|numeric|min:0',
            'iva_total' => 'required|numeric|min:0',
            'withholding_concept' => 'required|string|in:'.implode(',', array_keys(config('colombia_withholdings.concepts'))),
        ]);
        $concept = config('colombia_withholdings.concepts.'.$data['withholding_concept']);
        $base = $concept['base_on'] === 'iva' ? $data['iva_total'] : $data['subtotal'];
        $minimum = $concept['base_uvt'] * config('colombia_withholdings.uvt');
        $retention = $base >= $minimum ? round($base * $concept['rate'], 2) : 0;
        $data['retention_total'] = $retention;
        $data['total'] = round($data['subtotal'] + $data['iva_total'] - $retention, 2);
        $data['company_id'] = session('company_id');
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';
        $data['skip_dian'] = $request->boolean('skip_dian');

        try {
            $data['consecutive'] = NumberingResolution::allocateNext('support_document') ?? $data['consecutive'];
        } catch (\RuntimeException $e) {
            return back()->withErrors(['consecutive' => $e->getMessage()])->withInput();
        }

        $document = SupportDocument::create($data);

        $skipReason = null;
        $accountingVoucher = $this->accountingEntryService->postSupportDocument($document, $skipReason);

        $factusSkipReason = null;
        $sentToDian = $document->skip_dian ? false : $this->factusService->sendSupportDocumentInvoice($document, session('company_id'), $factusSkipReason);

        $message = $accountingVoucher
            ? 'Documento soporte guardado y contabilizado correctamente.'
            : 'Documento soporte guardado correctamente. No se contabilizó automáticamente: '.($skipReason ?? 'configura las cuentas PUC necesarias.');
        $message .= $document->skip_dian
            ? ' Marcado como documento interno: no se envía a la DIAN.'
            : ($sentToDian
                ? ' Enviado a la DIAN a través de Factus.'
                : ' No se envió a la DIAN: '.($factusSkipReason ?? 'error desconocido.'));

        return back()->with('success', $message);
    }

    public function xml(SupportDocument $document)
    {
        $document->load('supplier');
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SupportDocument></SupportDocument>');
        foreach (['Consecutive' => $document->consecutive, 'Date' => $document->document_date->toDateString(), 'Supplier' => $document->supplier->name, 'SupplierDocument' => $document->supplier->document, 'Concept' => $document->concept, 'Subtotal' => $document->subtotal, 'VAT' => $document->iva_total, 'Withholding' => $document->retention_total, 'Total' => $document->total] as $key => $value) {
            $xml->addChild($key, (string) $value);
        }

        return response($xml->asXML(), 200, ['Content-Type' => 'application/xml', 'Content-Disposition' => 'attachment; filename="'.$document->consecutive.'.xml"']);
    }

    public function email(Request $request, SupportDocument $document)
    {
        $data = $request->validate(['email' => 'required|email']);
        $document->load('supplier');
        Mail::raw("Documento soporte {$document->consecutive}\nProveedor: {$document->supplier->name}\nConcepto: {$document->concept}\nTotal: {$document->total}", fn ($message) => $message->to($data['email'])->subject('Documento soporte '.$document->consecutive));

        return back()->with('success', 'Documento soporte enviado por correo.');
    }
}
