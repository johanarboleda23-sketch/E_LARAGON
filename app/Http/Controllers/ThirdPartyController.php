<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\ThirdParty;
use App\Services\RutPdfExtractionService;
use App\Support\ColombianNit;
use App\Support\SpreadsheetReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ThirdPartyController extends Controller
{
    public function index()
    {
        $thirdParties = ThirdParty::with('contracts')->latest()->get();
        $employees = ThirdParty::where('is_employee', true)->where('active', true)->orderBy('name')->get();

        return view('third-parties.index', compact('thirdParties', 'employees'));
    }

    public function show(ThirdParty $thirdParty): View
    {
        abort_unless((int) $thirdParty->company_id === (int) session('company_id'), 404);
        $thirdParty->load(['createdBy:id,name,email', 'contracts']);

        return view('third-parties.show', compact('thirdParty'));
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $term = trim($data['q']);

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $thirdParties = ThirdParty::query()
            ->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('document', 'like', '%'.$term.'%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'document', 'verification_digit']);

        return response()->json([
            'data' => $thirdParties->map(fn (ThirdParty $thirdParty): array => [
                'name' => $thirdParty->name,
                'document' => $thirdParty->document,
                'verification_digit' => $thirdParty->verification_digit,
                'url' => route('third-parties.show', $thirdParty),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:natural,juridica',
            'name' => 'required|string|max:255',
            'document' => 'nullable|string|max:100',
            'verification_digit' => 'nullable|digits:1',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:150',
            'neighborhood' => 'nullable|string|max:150',
            'fiscal_responsibilities' => ['nullable', 'string', 'max:512', 'regex:/^[A-Za-z0-9,\\s;-]*$/'],
            'is_customer' => 'nullable|boolean',
            'is_supplier' => 'nullable|boolean',
            'is_employee' => 'nullable|boolean',
        ]);
        $data['fiscal_responsibilities'] = $this->normalizeFiscalResponsibilities($data['fiscal_responsibilities'] ?? null);
        abort_if(empty($data['is_customer']) && empty($data['is_supplier']) && empty($data['is_employee']), 422, 'Selecciona al menos un perfil.');
        $this->applyVerificationDigit($data);
        ThirdParty::create([...$data, 'created_by' => $request->user()->id, 'is_customer' => ! empty($data['is_customer']), 'is_supplier' => ! empty($data['is_supplier']), 'is_employee' => ! empty($data['is_employee']), 'active' => true]);

        return back()->with('success', 'Tercero registrado correctamente.');
    }

    public function update(Request $request, ThirdParty $thirdParty)
    {
        $data = $request->validate([
            'type' => 'required|in:natural,juridica',
            'name' => 'required|string|max:255',
            'document' => 'nullable|string|max:100',
            'verification_digit' => 'nullable|digits:1',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:150',
            'neighborhood' => 'nullable|string|max:150',
            'fiscal_responsibilities' => ['nullable', 'string', 'max:512', 'regex:/^[A-Za-z0-9,\\s;-]*$/'],
            'is_customer' => 'nullable|boolean',
            'is_supplier' => 'nullable|boolean',
            'is_employee' => 'nullable|boolean',
            'active' => 'required|boolean',
        ]);
        $data['fiscal_responsibilities'] = $this->normalizeFiscalResponsibilities($data['fiscal_responsibilities'] ?? null);
        $data['is_customer'] = ! empty($data['is_customer']);
        $data['is_supplier'] = ! empty($data['is_supplier']);
        $data['is_employee'] = ! empty($data['is_employee']);
        abort_if(! $data['is_customer'] && ! $data['is_supplier'] && ! $data['is_employee'], 422, 'Selecciona al menos un perfil.');
        $this->applyVerificationDigit($data, $thirdParty->document);
        $thirdParty->update($data);

        return back()->with('success', 'Tercero actualizado.');
    }

    public function extractRut(Request $request, RutPdfExtractionService $rutPdfExtractionService): JsonResponse
    {
        $data = $request->validate([
            'rut_pdf' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $extracted = $rutPdfExtractionService->extract($data['rut_pdf']);

        if (count(array_filter($extracted, fn (?string $value): bool => $value !== null)) === 0) {
            throw ValidationException::withMessages([
                'rut_pdf' => 'No se encontraron datos legibles. Carga un PDF digital del RUT, no una imagen escaneada.',
            ]);
        }

        return response()->json(['data' => $extracted]);
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
            ['Tipo persona (natural/juridica)', 'Nombre', 'Documento / NIT', 'Correo', 'Teléfono', 'Es cliente (si/no)', 'Es proveedor (si/no)', 'Es empleado (si/no)', 'DV (si aplica)', 'Responsabilidades fiscales del RUT (códigos separados por coma)'],
            ['natural', 'Juan Pérez', '123456789', 'juan@example.com', '3001234567', 'si', 'no', 'no', '', ''],
            ['juridica', 'Proveedores ACME S.A.S.', '800197268', 'contacto@acme.com', '6012345678', 'no', 'si', 'no', '4', ''],
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
            if (array_key_exists(8, $row)) {
                $verificationDigit = trim((string) $row[8]);
                if ($verificationDigit !== '' && preg_match('/^\d$/', $verificationDigit) !== 1) {
                    $skipped++;

                    continue;
                }

                $attributes['verification_digit'] = $verificationDigit !== '' ? $verificationDigit : null;
            }
            if (array_key_exists(9, $row)) {
                $fiscalResponsibilities = trim((string) $row[9]);
                if (preg_match('/^[A-Za-z0-9,\\s;-]*$/', $fiscalResponsibilities) !== 1) {
                    $skipped++;

                    continue;
                }

                $attributes['fiscal_responsibilities'] = $this->normalizeFiscalResponsibilities($fiscalResponsibilities);
            }

            if (! $attributes['is_customer'] && ! $attributes['is_supplier'] && ! $attributes['is_employee']) {
                $skipped++;

                continue;
            }

            $this->applyVerificationDigit($attributes);
            $thirdParty = $document ? ThirdParty::query()->where('document', $document)->first() : null;
            if ($thirdParty) {
                $thirdParty->update($attributes);
                $updated++;
            } else {
                ThirdParty::create([...$attributes, 'created_by' => auth()->id()]);
                $created++;
            }
        }

        return back()->with('success', "Migración de terceros completada: {$created} creados, {$updated} actualizados, {$skipped} omitidos por datos incompletos.");
    }

    private function parseBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['si', 'sí', 'yes', '1', 'true'], true);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeFiscalResponsibilities(?string $responsibilities): array
    {
        $codes = preg_split('/[,;\\s]+/', strtoupper(trim($responsibilities ?? '')), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($codes ?: []));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyVerificationDigit(array &$attributes, ?string $existingDocument = null): void
    {
        if (($attributes['type'] ?? null) === 'juridica') {
            $attributes['verification_digit'] = ColombianNit::verificationDigit(
                $attributes['document'] ?? $existingDocument,
            );
        }
    }
}
