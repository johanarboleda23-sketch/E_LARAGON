<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        $accounts = ChartOfAccount::with('parent')->orderBy('code')->get();
        $parents = ChartOfAccount::whereIn('account_type', ['clase', 'mayor'])->orderBy('code')->get();

        return view('accounting.puc.index', compact('accounts', 'parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('chart_of_accounts', 'code')->where('company_id', session('company_id'))],
            'name' => 'required|string|max:255',
            'class' => 'required|integer|between:1,9',
            'nature' => 'required|in:debit,credit',
            'account_type' => 'required|in:clase,mayor,auxiliar',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'has_due_date' => 'boolean',
        ]);
        $data['allows_posting'] = $data['account_type'] === 'auxiliar';
        $data['active'] = true;
        ChartOfAccount::create($data);

        return back()->with('success', 'Cuenta PUC creada correctamente.');
    }

    public function update(Request $request, ChartOfAccount $account)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nature' => 'required|in:debit,credit',
            'account_type' => 'required|in:clase,mayor,auxiliar',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'has_due_date' => 'boolean',
            'active' => 'boolean',
        ]);
        $data['allows_posting'] = $data['account_type'] === 'auxiliar';
        $account->update($data);

        return back()->with('success', 'Cuenta PUC actualizada.');
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
        ]);

        $codeToId = [];
        $created = 0;
        $updated = 0;

        foreach ($this->rowsFromFile($data['file']->getRealPath(), $data['file']->getClientOriginalExtension()) as $row) {
            $code = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            if ($code === '' || $name === '' || ! ctype_digit($code) || ! in_array(strlen($code), [1, 2, 4, 6, 8], true)) {
                continue;
            }

            $level = strlen($code);
            $parentCode = match (true) {
                $level >= 8 => substr($code, 0, 6),
                $level >= 6 => substr($code, 0, 4),
                $level >= 4 => substr($code, 0, 2),
                $level >= 2 => substr($code, 0, 1),
                default => null,
            };

            $class = (int) $code[0];
            $vencimientos = strtolower(trim((string) ($row[5] ?? '')));
            $activo = strtolower(trim((string) ($row[7] ?? '')));

            $attributes = [
                'name' => $name,
                'class' => $class,
                'nature' => in_array($class, [1, 5, 6, 7, 8], true) ? 'debit' : 'credit',
                'account_type' => match ($level) {
                    1 => 'clase',
                    2 => 'grupo',
                    4 => 'cuenta',
                    6 => 'subcuenta',
                    default => 'auxiliar',
                },
                'allows_posting' => trim((string) ($row[8] ?? '')) !== '',
                'has_due_date' => $vencimientos !== '' && ! str_starts_with($vencimientos, 'no'),
                'active' => $activo !== 'no',
                'parent_id' => $parentCode ? ($codeToId[$parentCode] ?? null) : null,
            ];

            $account = ChartOfAccount::query()->where('code', $code)->first();
            if ($account) {
                $account->update($attributes);
                $updated++;
            } else {
                $account = ChartOfAccount::create($attributes + ['code' => $code]);
                $created++;
            }

            $codeToId[$code] = $account->id;
        }

        return back()->with('success', "PUC importado: {$created} cuentas creadas, {$updated} actualizadas.");
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rowsFromFile(string $path, string $extension): array
    {
        if (strtolower($extension) === 'xlsx') {
            return $this->rowsFromXlsx($path);
        }

        $handle = fopen($path, 'r');
        $rows = [];
        $firstLine = fgets($handle);
        $delimiter = str_contains((string) $firstLine, ';') ? ';' : ',';
        if ($firstLine !== false) {
            $rows[] = str_getcsv($firstLine, $delimiter);
        }

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (count($row) === 1 && str_contains($row[0], $delimiter)) {
                $row = str_getcsv($row[0], $delimiter);
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rowsFromXlsx(string $path): array
    {
        $archive = new \ZipArchive;
        abort_unless($archive->open($path) === true, 422, 'No se pudo abrir el archivo Excel.');

        $sharedStrings = [];
        if (($sharedXml = $archive->getFromName('xl/sharedStrings.xml')) !== false) {
            $shared = simplexml_load_string($sharedXml);
            foreach ($shared->si as $string) {
                $sharedStrings[] = implode('', array_map('strval', $string->xpath('.//*[local-name()="t"]') ?: []));
            }
        }

        $sheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();
        abort_unless($sheetXml !== false, 422, 'El archivo Excel no contiene una hoja válida.');

        $sheet = simplexml_load_string($sheetXml);
        $namespaces = $sheet->getNamespaces(true);
        $uri = $namespaces[''] ?? $namespaces['x'] ?? null;
        $sheetData = $uri ? $sheet->children($uri)->sheetData : $sheet->sheetData;

        $rows = [];
        foreach ($sheetData->children($uri) as $sheetRow) {
            $row = [];
            foreach ($sheetRow->children($uri) as $cell) {
                $value = (string) ($uri ? $cell->children($uri)->v : $cell->v);
                if ((string) ($cell['t'] ?? '') === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $row[] = $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
