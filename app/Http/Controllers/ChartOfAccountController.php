<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Support\SpreadsheetReader;
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
        return SpreadsheetReader::rows($path, $extension);
    }
}
