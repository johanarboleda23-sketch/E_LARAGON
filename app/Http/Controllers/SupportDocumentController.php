<?php

namespace App\Http\Controllers;

use App\Models\SupportDocument;
use App\Models\ThirdParty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportDocumentController extends Controller
{
    public function index()
    {
        $suppliers = ThirdParty::where('is_supplier', true)->where('active', true)->orderBy('name')->get();
        $documents = SupportDocument::with(['supplier', 'creator'])->latest()->take(20)->get();
        $withholdings = config('colombia_withholdings');

        return view('support-documents.index', compact('suppliers', 'documents', 'withholdings'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'consecutive' => 'required|string|max:60',
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
        SupportDocument::create($data);

        return back()->with('success', 'Documento soporte guardado correctamente.');
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
