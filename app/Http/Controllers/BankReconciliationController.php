<?php

namespace App\Http\Controllers;

use App\Models\BankStatement;
use App\Models\Purchase;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->value();
        $statements = BankStatement::query()
            ->when(in_array($status, ['pending', 'reconciled'], true), fn ($query) => $query->where('status', $status))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'pending' => BankStatement::query()->where('status', 'pending')->sum('amount'),
            'reconciled' => BankStatement::query()->where('status', 'reconciled')->sum('amount'),
            'count' => BankStatement::query()->count(),
        ];

        return view('bank-reconciliation.index', compact('statements', 'status', 'summary'));
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        $created = 0;

        foreach ($this->rowsFromFile($data['file']->getRealPath(), $data['file']->getClientOriginalExtension()) as $row) {
            if (count(array_filter($row)) === 0) {
                continue;
            }

            if ($this->isHeaderRow($row)) {
                continue;
            }

            $compactFormat = count($row) <= 3;
            $date = $this->parseDate($row[0] ?? '');
            $description = trim((string) ($compactFormat ? ($row[1] ?? '') : ($row[2] ?? '')));
            $legacyType = strtolower(trim((string) ($compactFormat ? '' : ($row[3] ?? ''))));
            $rawAmount = trim((string) ($compactFormat ? ($row[2] ?? '0') : ($row[4] ?? '0')));
            $amount = $this->parseAmount($rawAmount);

            if (! $date) {
                continue;
            }

            $type = $legacyType !== ''
                ? $legacyType
                : ($amount < 0 ? 'debit' : 'credit');

            if (! in_array($type, ['credit', 'debit', 'ingreso', 'egreso'], true) || $amount == 0) {
                continue;
            }

            $transactionType = in_array($type, ['credit', 'ingreso'], true) ? 'credit' : 'debit';
            if ((float) $rawAmount < 0) {
                $transactionType = 'debit';
            }

            BankStatement::create([
                'transaction_date' => $date,
                'reference' => $compactFormat ? null : (trim($row[1] ?? '') ?: null),
                'description' => $description ?: null,
                'transaction_type' => $transactionType,
                'amount' => abs($amount),
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);
            $created++;
        }

        return back()->with('success', "Se importaron {$created} movimientos bancarios.");
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
        $rows = [];
        foreach ($sheet->sheetData->row as $sheetRow) {
            $row = [];
            foreach ($sheetRow->c as $cell) {
                $value = (string) ($cell->v ?? '');
                if ((string) ($cell['t'] ?? '') === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $row[] = $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function isHeaderRow(array $row): bool
    {
        $normalized = strtolower(implode('|', array_map('trim', $row)));

        return str_contains($normalized, 'fecha') && (str_contains($normalized, 'valor') || str_contains($normalized, 'importe'));
    }

    private function parseDate(mixed $value): ?string
    {
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmount(string $value): float
    {
        $normalized = trim(str_replace(['$', ' '], '', $value));
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (float) $normalized;
    }

    public function reconcile(Request $request, BankStatement $statement): RedirectResponse
    {
        $data = $request->validate([
            'matched_type' => ['nullable', 'string', 'in:sale,purchase,manual'],
            'matched_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['matched_type'] === 'sale') {
            abort_unless(Sale::query()->whereKey($data['matched_id'])->exists(), 422);
        }

        if ($data['matched_type'] === 'purchase') {
            abort_unless(Purchase::query()->whereKey($data['matched_id'])->exists(), 422);
        }

        $statement->update([
            'status' => 'reconciled',
            'matched_type' => $data['matched_type'] ?? 'manual',
            'matched_id' => $data['matched_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'reconciled_at' => now(),
            'reconciled_by' => auth()->id(),
        ]);

        return back()->with('success', 'Movimiento conciliado correctamente.');
    }

    public function unmatch(BankStatement $statement): RedirectResponse
    {
        $statement->update([
            'status' => 'pending',
            'matched_type' => null,
            'matched_id' => null,
            'reconciled_at' => null,
            'reconciled_by' => null,
        ]);

        return back()->with('success', 'Movimiento devuelto a pendientes.');
    }

    public function suggestions(BankStatement $statement): JsonResponse
    {
        $dateFrom = $statement->transaction_date->copy()->subDays(30);
        $dateTo = $statement->transaction_date->copy()->addDays(30);

        return response()->json([
            'sales' => Sale::query()
                ->where('total', $statement->amount)
                ->whereBetween('sale_date', [$dateFrom, $dateTo])
                ->latest('id')
                ->limit(5)
                ->get(['id', 'invoice_number', 'customer_name', 'sale_date', 'total'])
                ->all(),
            'purchases' => Purchase::query()
                ->where('total_pagar', $statement->amount)
                ->whereBetween('purchase_date', [$dateFrom, $dateTo])
                ->latest('id')
                ->limit(5)
                ->get(['id', 'invoice_number', 'provider', 'purchase_date', 'total_pagar'])
                ->all(),
        ]);
    }
}
