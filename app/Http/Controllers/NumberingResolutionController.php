<?php

namespace App\Http\Controllers;

use App\Models\NumberingResolution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NumberingResolutionController extends Controller
{
    public const DOCUMENT_TYPES = [
        'sale' => 'Factura de venta',
        'purchase' => 'Factura de compra',
        'quotation' => 'Cotización',
        'sales_order' => 'Orden de venta',
        'remission' => 'Remisión',
        'purchase_order' => 'Orden de compra',
        'customer_credit_note' => 'Nota crédito clientes',
        'supplier_debit_note' => 'Nota débito proveedores',
        'support_document' => 'Documento soporte',
    ];

    public function index(): View
    {
        $resolutions = NumberingResolution::query()->latest('id')->get();

        return view('numbering-resolutions.index', [
            'resolutions' => $resolutions,
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::DOCUMENT_TYPES))],
            'prefix' => ['nullable', 'string', 'max:20'],
            'resolution_number' => ['required', 'string', 'max:50'],
            'resolution_date' => ['nullable', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'range_from' => ['required', 'integer', 'min:1'],
            'range_to' => ['required', 'integer', 'gt:range_from'],
        ]);

        NumberingResolution::create($data + ['next_number' => $data['range_from'], 'active' => true]);

        return back()->with('success', 'Resolución de numeración registrada.');
    }

    public function update(Request $request, NumberingResolution $numberingResolution): RedirectResponse
    {
        $data = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'resolution_number' => ['sometimes', 'string', 'max:50'],
            'resolution_date' => ['sometimes', 'nullable', 'date'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'range_from' => ['sometimes', 'integer', 'min:1'],
            'range_to' => ['sometimes', 'integer', 'gt:range_from'],
            'next_number' => ['sometimes', 'integer', 'min:1'],
        ]);

        $numberingResolution->update($data);

        return back()->with('success', array_key_exists('active', $data) && count($data) === 1
            ? ($data['active'] ? 'Resolución activada.' : 'Resolución desactivada.')
            : 'Resolución actualizada.');
    }

    public function destroy(NumberingResolution $numberingResolution): RedirectResponse
    {
        $numberingResolution->delete();

        return back()->with('success', 'Resolución eliminada.');
    }
}
