<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\ThirdParty;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ThirdPartyController extends Controller
{
    public function index()
    {
        $thirdParties = ThirdParty::with('contracts')->latest()->get();
        $employees = ThirdParty::where('is_employee', true)->where('active', true)->orderBy('name')->get();

        return view('third-parties.index', compact('thirdParties', 'employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:natural,juridica',
            'name' => 'required|string|max:255',
            'document' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'is_customer' => 'nullable|boolean',
            'is_supplier' => 'nullable|boolean',
            'is_employee' => 'nullable|boolean',
        ]);
        abort_if(empty($data['is_customer']) && empty($data['is_supplier']) && empty($data['is_employee']), 422, 'Selecciona al menos un perfil.');
        ThirdParty::create([...$data, 'is_customer' => ! empty($data['is_customer']), 'is_supplier' => ! empty($data['is_supplier']), 'is_employee' => ! empty($data['is_employee']), 'active' => true]);

        return back()->with('success', 'Tercero registrado correctamente.');
    }

    public function update(Request $request, ThirdParty $thirdParty)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'document' => 'nullable|string|max:100', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50', 'is_customer' => 'nullable|boolean', 'is_supplier' => 'nullable|boolean', 'is_employee' => 'nullable|boolean', 'active' => 'nullable|boolean']);
        $data['is_customer'] = ! empty($data['is_customer']);
        $data['is_supplier'] = ! empty($data['is_supplier']);
        $data['is_employee'] = ! empty($data['is_employee']);
        $thirdParty->update($data);

        return back()->with('success', 'Tercero actualizado.');
    }

    public function toggleActive(ThirdParty $thirdParty)
    {
        $thirdParty->update(['active' => ! $thirdParty->active]);

        return back()->with('success', 'Estado del tercero actualizado.');
    }

    public function storeContract(Request $request)
    {
        $data = $request->validate([
            'third_party_id' => 'required|exists:third_parties,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'salary' => 'required|numeric|min:0',
            'contract_type' => 'required|in:indefinido,fijo,obra_labor',
        ]);
        $employee = ThirdParty::where('is_employee', true)->findOrFail($data['third_party_id']);
        $initial = mb_strtoupper(mb_substr(trim($employee->name), 0, 1));
        $number = EmployeeContract::count() + 1;
        $data['company_id'] = session('company_id');
        $data['employee_initial'] = $initial;
        $data['consecutive'] = 'CTR-'.$initial.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
        $data['active'] = true;
        EmployeeContract::create($data);

        return back()->with('success', 'Contrato creado: '.$data['consecutive']);
    }

    public function toggleContract(EmployeeContract $contract)
    {
        $contract->update(['active' => ! $contract->active]);

        return back()->with('success', 'Estado del contrato actualizado.');
    }

    public function template(): Response
    {
        $rows = [
            ['Tipo persona (natural/juridica)', 'Nombre', 'Documento', 'Correo', 'Teléfono', 'Es cliente (si/no)', 'Es proveedor (si/no)', 'Es empleado (si/no)'],
            ['natural', 'Juan Pérez', '123456789', 'juan@example.com', '3001234567', 'si', 'no', 'no'],
            ['juridica', 'Proveedores ACME S.A.S.', '900123456', 'contacto@acme.com', '6012345678', 'no', 'si', 'no'],
        ];

        $content = collect($rows)
            ->map(fn (array $row) => collect($row)->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')->implode(';'))
            ->implode("\r\n");

        return response("\xEF\xBB\xBF".$content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-terceros.csv"',
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
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $type = strtolower(trim((string) ($row[0] ?? '')));
            $name = trim((string) ($row[1] ?? ''));

            if ($name === '' || ! in_array($type, ['natural', 'juridica'], true)) {
                $skipped++;

                continue;
            }

            $document = trim((string) ($row[2] ?? '')) ?: null;
            $attributes = [
                'type' => $type,
                'name' => $name,
                'document' => $document,
                'email' => trim((string) ($row[3] ?? '')) ?: null,
                'phone' => trim((string) ($row[4] ?? '')) ?: null,
                'is_customer' => $this->parseBoolean($row[5] ?? null),
                'is_supplier' => $this->parseBoolean($row[6] ?? null),
                'is_employee' => $this->parseBoolean($row[7] ?? null),
                'active' => true,
            ];

            if (! $attributes['is_customer'] && ! $attributes['is_supplier'] && ! $attributes['is_employee']) {
                $skipped++;

                continue;
            }

            $thirdParty = $document ? ThirdParty::query()->where('document', $document)->first() : null;
            if ($thirdParty) {
                $thirdParty->update($attributes);
                $updated++;
            } else {
                ThirdParty::create($attributes);
                $created++;
            }
        }

        return back()->with('success', "Migración de terceros completada: {$created} creados, {$updated} actualizados, {$skipped} omitidos por datos incompletos.");
    }

    private function parseBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['si', 'sí', 'yes', '1', 'true'], true);
    }
}
