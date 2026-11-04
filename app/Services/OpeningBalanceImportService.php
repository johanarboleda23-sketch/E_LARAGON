<?php

namespace App\Services;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\OpeningBalanceInvoice;
use App\Models\ThirdParty;
use App\Support\SpreadsheetReader;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceImportService
{
    private const HEADERS = [
        'Tipo cartera',
        'Documento tercero',
        'Factura',
        'Fecha factura',
        'Fecha vencimiento',
        'CUFE',
        'Cuenta PUC',
        'Débito',
        'Crédito',
        'Detalle',
    ];

    public function __construct(
        private readonly AccountingEntryService $accountingEntryService,
        private readonly DianInvoiceXmlReader $xmlReader,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $xmlFiles
     */
    public function import(UploadedFile $spreadsheet, array $xmlFiles, string $voucherDate, string $consecutive): AccountingVoucher
    {
        $path = $spreadsheet->getRealPath();
        if ($path === false) {
            throw new \RuntimeException('No fue posible leer el archivo de saldos iniciales.');
        }

        $rows = SpreadsheetReader::rows($path, $spreadsheet->getClientOriginalExtension());
        $headers = array_map(static fn ($header): string => trim((string) $header), array_shift($rows) ?? []);
        if (isset($headers[0])) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        }
        if ($headers !== self::HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'La primera fila debe coincidir con la plantilla de saldos iniciales.',
            ]);
        }

        $rows = array_values(array_filter($rows, static function (array $row): bool {
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }

                continue;
            }

            return false;
        }));
        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'El archivo no contiene saldos para importar.',
            ]);
        }
        if (count($rows) > 500) {
            throw ValidationException::withMessages([
                'file' => 'La plantilla admite máximo 500 saldos por carga.',
            ]);
        }

        $xmlInvoices = $this->readXmlFiles($xmlFiles);
        $thirdParties = $this->findThirdParties($rows);
        $accounts = $this->findAccounts($rows);
        $records = [];
        $usedXml = [];
        $seenInvoices = [];
        $seenCufes = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            if (count($row) > count(self::HEADERS) && count(array_filter(
                array_slice($row, count(self::HEADERS)),
                static fn ($value): bool => trim((string) $value) !== '',
            )) > 0) {
                $errors[] = "Fila {$rowNumber}: hay datos en columnas que no pertenecen a la plantilla.";

                continue;
            }

            $invoiceType = mb_strtolower(trim((string) ($row[0] ?? '')), 'UTF-8');
            $thirdPartyDocument = trim((string) ($row[1] ?? ''));
            $invoiceNumber = trim((string) ($row[2] ?? ''));
            $invoiceDate = trim((string) ($row[3] ?? ''));
            $dueDate = trim((string) ($row[4] ?? ''));
            $cufe = mb_strtoupper(trim((string) ($row[5] ?? '')), 'UTF-8');
            $accountCode = trim((string) ($row[6] ?? ''));
            $debit = $this->parseAmount((string) ($row[7] ?? ''));
            $credit = $this->parseAmount((string) ($row[8] ?? ''));
            $detail = trim((string) ($row[9] ?? ''));

            if ($accountCode === '' || $debit === null || $credit === null || $debit < 0 || $credit < 0 || (($debit > 0) === ($credit > 0))) {
                $errors[] = "Fila {$rowNumber}: indica una cuenta PUC, valores no negativos y un único valor débito o crédito mayor que cero.";

                continue;
            }

            if (mb_strlen($detail, 'UTF-8') > 255) {
                $errors[] = "Fila {$rowNumber}: el detalle no puede superar 255 caracteres.";

                continue;
            }

            $account = $accounts->get($accountCode);
            if (! $account) {
                $errors[] = "Fila {$rowNumber}: la cuenta PUC {$accountCode} no existe o no permite contabilización directa.";

                continue;
            }

            $line = [
                'chart_of_account_id' => $account->id,
                'detail' => $detail !== '' ? $detail : null,
                'debit' => $debit,
                'credit' => $credit,
            ];
            $invoiceMetadataPresent = $invoiceType !== ''
                || $thirdPartyDocument !== ''
                || $invoiceNumber !== ''
                || $invoiceDate !== ''
                || $dueDate !== ''
                || $cufe !== '';

            if (! $invoiceMetadataPresent) {
                $records[] = ['line' => $line, 'invoice' => null];

                continue;
            }

            if (! in_array($invoiceType, ['cliente', 'proveedor'], true)
                || $thirdPartyDocument === ''
                || $invoiceNumber === ''
                || mb_strlen($thirdPartyDocument, 'UTF-8') > 100
                || mb_strlen($invoiceNumber, 'UTF-8') > 100
                || mb_strlen($cufe, 'UTF-8') > 180) {
                $errors[] = "Fila {$rowNumber}: verifica tipo de cartera, documento y número de factura (máximo 100 caracteres); el CUFE no puede superar 180 caracteres.";

                continue;
            }

            if (($invoiceType === 'cliente' && ($debit <= 0 || $credit > 0))
                || ($invoiceType === 'proveedor' && ($credit <= 0 || $debit > 0))) {
                $errors[] = "Fila {$rowNumber}: cartera de clientes debe ir al débito y cartera de proveedores al crédito.";

                continue;
            }

            $thirdParty = $thirdParties->get($thirdPartyDocument);
            if (! $thirdParty
                || ($invoiceType === 'cliente' && ! $thirdParty->is_customer)
                || ($invoiceType === 'proveedor' && ! $thirdParty->is_supplier)) {
                $errors[] = "Fila {$rowNumber}: el documento {$thirdPartyDocument} no corresponde a un tercero activo con perfil de ".($invoiceType === 'cliente' ? 'cliente' : 'proveedor').'.';

                continue;
            }

            $xmlMatch = $this->matchXmlInvoice($xmlInvoices, $invoiceNumber, $invoiceType, $thirdPartyDocument);
            if ($xmlMatch['error'] !== null) {
                $errors[] = "Fila {$rowNumber}: {$xmlMatch['error']}";

                continue;
            }
            if ($xmlMatch['file_index'] !== null) {
                $usedXml[$xmlMatch['file_index']] = true;
                $xml = $xmlMatch['invoice'];
                if ($cufe !== '' && ! hash_equals($cufe, mb_strtoupper($xml['cufe'], 'UTF-8'))) {
                    $errors[] = "Fila {$rowNumber}: el CUFE del Excel no coincide con el CUFE del XML.";

                    continue;
                }
                $cufe = mb_strtoupper($xml['cufe'], 'UTF-8');
                $invoiceDate = $invoiceDate !== '' ? $invoiceDate : (string) ($xml['invoice_date'] ?? '');
                $dueDate = $dueDate !== '' ? $dueDate : (string) ($xml['due_date'] ?? '');
            }

            $normalizedInvoiceNumber = mb_strtolower($invoiceNumber, 'UTF-8');
            $invoiceKey = $invoiceType.'|'.$thirdParty->id.'|'.$normalizedInvoiceNumber;
            if (isset($seenInvoices[$invoiceKey])) {
                $errors[] = "Fila {$rowNumber}: la factura {$invoiceNumber} está repetida en la plantilla.";

                continue;
            }
            $seenInvoices[$invoiceKey] = true;

            if ($cufe !== '') {
                if (isset($seenCufes[$cufe])) {
                    $errors[] = "Fila {$rowNumber}: el CUFE está repetido en la plantilla.";

                    continue;
                }
                $seenCufes[$cufe] = true;
            }

            $normalizedInvoiceDate = $this->normalizeDate($invoiceDate);
            $normalizedDueDate = $dueDate !== '' ? $this->normalizeDate($dueDate) : null;
            if ($normalizedInvoiceDate === null || ($dueDate !== '' && $normalizedDueDate === null)) {
                $errors[] = "Fila {$rowNumber}: utiliza fechas válidas (AAAA-MM-DD o DD/MM/AAAA).";

                continue;
            }
            if ($normalizedDueDate !== null && $normalizedDueDate < $normalizedInvoiceDate) {
                $errors[] = "Fila {$rowNumber}: el vencimiento no puede ser anterior a la fecha de factura.";

                continue;
            }

            $amount = $invoiceType === 'cliente' ? $debit : $credit;
            $records[] = [
                'line' => [
                    ...$line,
                    'detail' => $detail !== '' ? $detail : 'Saldo inicial factura '.$invoiceNumber.' - '.$thirdParty->name,
                ],
                'invoice' => [
                    'invoice_type' => $invoiceType,
                    'third_party_id' => $thirdParty->id,
                    'third_party_name' => $thirdParty->name,
                    'third_party_document' => $thirdParty->document,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $normalizedInvoiceDate,
                    'due_date' => $normalizedDueDate,
                    'cufe' => $cufe !== '' ? $cufe : null,
                    'opening_balance' => $amount,
                ],
            ];
        }

        foreach ($xmlInvoices as $xmlIndex => $xmlEntry) {
            if (! isset($usedXml[$xmlIndex])) {
                $errors[] = 'El XML '.$xmlEntry['name'].' no coincide con ninguna factura de la plantilla.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => implode(' ', array_slice($errors, 0, 20)).(count($errors) > 20 ? ' Hay más errores en el archivo.' : ''),
            ]);
        }

        $lines = collect($records)->pluck('line');
        $totalDebit = round($lines->sum('debit'), 2);
        $totalCredit = round($lines->sum('credit'), 2);
        if ($totalDebit <= 0 || $totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'file' => 'Los saldos deben cuadrar: la suma de débitos debe ser igual a la suma de créditos.',
            ]);
        }

        $this->ensureInvoicesAreNew($records);

        return DB::transaction(function () use ($records, $lines, $voucherDate, $consecutive): AccountingVoucher {
            $voucher = $this->accountingEntryService->post([
                'voucher_type' => 'saldos_iniciales',
                'consecutive' => $consecutive,
                'voucher_date' => $voucherDate,
                'third_party' => 'Carga masiva de cartera inicial',
                'description' => 'Saldos iniciales importados desde plantilla.',
            ], $lines);

            $voucherLines = $voucher->lines()->orderBy('id')->get();
            foreach ($records as $index => $record) {
                if ($record['invoice'] === null) {
                    continue;
                }

                OpeningBalanceInvoice::create([
                    ...$record['invoice'],
                    'accounting_voucher_id' => $voucher->id,
                    'accounting_voucher_line_id' => $voucherLines[$index]->id,
                ]);
            }

            return $voucher;
        });
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return Collection<string, ThirdParty>
     */
    private function findThirdParties(array $rows): Collection
    {
        $documents = collect($rows)
            ->map(static fn (array $row): string => trim((string) ($row[1] ?? '')))
            ->filter()
            ->unique()
            ->values();

        return ThirdParty::query()
            ->where('active', true)
            ->whereIn('document', $documents)
            ->where(static fn ($query) => $query->where('is_customer', true)->orWhere('is_supplier', true))
            ->get(['id', 'name', 'document', 'is_customer', 'is_supplier'])
            ->keyBy('document');
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return Collection<string, ChartOfAccount>
     */
    private function findAccounts(array $rows): Collection
    {
        $codes = collect($rows)
            ->map(static fn (array $row): string => trim((string) ($row[6] ?? '')))
            ->filter()
            ->unique()
            ->values();

        return ChartOfAccount::query()
            ->whereIn('code', $codes)
            ->where('active', true)
            ->where('allows_posting', true)
            ->get(['id', 'code'])
            ->keyBy('code');
    }

    /**
     * @param  array<int, UploadedFile>  $xmlFiles
     * @return array<int, array{name: string, invoice: array<string, ?string>}>
     */
    private function readXmlFiles(array $xmlFiles): array
    {
        $invoices = [];
        $errors = [];

        foreach ($xmlFiles as $index => $file) {
            try {
                $invoices[$index] = [
                    'name' => $file->getClientOriginalName(),
                    'invoice' => $this->xmlReader->read($file->getPathname()),
                ];
            } catch (ValidationException $exception) {
                $errors[] = $file->getClientOriginalName().': '.$exception->validator->errors()->first();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'xml_files' => implode(' ', $errors),
            ]);
        }

        return $invoices;
    }

    /**
     * @param  array<int, array{name: string, invoice: array<string, ?string>}>  $xmlInvoices
     * @return array{file_index: int|null, invoice: array<string, ?string>|null, error: string|null}
     */
    private function matchXmlInvoice(array $xmlInvoices, string $invoiceNumber, string $invoiceType, string $thirdPartyDocument): array
    {
        $matchingNumber = [];
        foreach ($xmlInvoices as $index => $entry) {
            if (mb_strtolower(trim((string) $entry['invoice']['invoice_number']), 'UTF-8') === mb_strtolower($invoiceNumber, 'UTF-8')) {
                $matchingNumber[$index] = $entry;
            }
        }

        if ($matchingNumber === []) {
            return ['file_index' => null, 'invoice' => null, 'error' => null];
        }

        $partyField = $invoiceType === 'cliente' ? 'customer_document' : 'supplier_document';
        $normalizedDocument = $this->normalizeDocument($thirdPartyDocument);
        $matchingParty = array_filter($matchingNumber, fn (array $entry): bool => $this->normalizeDocument((string) ($entry['invoice'][$partyField] ?? '')) === $normalizedDocument);

        if (count($matchingParty) === 1) {
            $index = array_key_first($matchingParty);

            return ['file_index' => $index, 'invoice' => $matchingParty[$index]['invoice'], 'error' => null];
        }
        if (count($matchingParty) > 1 || count($matchingNumber) > 1) {
            return ['file_index' => null, 'invoice' => null, 'error' => "hay más de un XML posible para la factura {$invoiceNumber}; verifica el documento del tercero."];
        }

        $candidate = reset($matchingNumber);
        $xmlDocument = $candidate['invoice'][$partyField] ?? null;
        if ($xmlDocument !== null && $this->normalizeDocument($xmlDocument) !== $normalizedDocument) {
            return ['file_index' => null, 'invoice' => null, 'error' => "el documento del tercero no coincide con el XML de la factura {$invoiceNumber}."];
        }

        $index = array_key_first($matchingNumber);

        return ['file_index' => $index, 'invoice' => $candidate['invoice'], 'error' => null];
    }

    /**
     * @param  array<int, array{line: array<string, mixed>, invoice: array<string, mixed>|null}>  $records
     */
    private function ensureInvoicesAreNew(array $records): void
    {
        $invoices = collect($records)
            ->pluck('invoice')
            ->filter()
            ->values();
        if ($invoices->isEmpty()) {
            return;
        }

        $invoiceNumbers = $invoices->pluck('invoice_number')->unique();
        $existing = OpeningBalanceInvoice::query()
            ->whereIn('invoice_number', $invoiceNumbers)
            ->get(['invoice_type', 'third_party_id', 'invoice_number']);
        foreach ($invoices as $invoice) {
            if ($existing->contains(fn (OpeningBalanceInvoice $record): bool => $record->invoice_type === $invoice['invoice_type']
                && $record->third_party_id === $invoice['third_party_id']
                && mb_strtolower($record->invoice_number, 'UTF-8') === mb_strtolower($invoice['invoice_number'], 'UTF-8'))) {
                throw ValidationException::withMessages([
                    'file' => 'La factura '.$invoice['invoice_number'].' ya tiene un saldo inicial importado.',
                ]);
            }
        }

        $cufes = $invoices->pluck('cufe')->filter()->unique();
        if ($cufes->isNotEmpty() && OpeningBalanceInvoice::query()->whereIn('cufe', $cufes)->exists()) {
            throw ValidationException::withMessages([
                'file' => 'Uno de los CUFE ya está registrado en saldos iniciales.',
            ]);
        }
    }

    private function parseAmount(string $value): ?float
    {
        $value = trim(str_replace(['$', ' '], '', $value));
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = preg_match('/,\d{1,2}$/', $value) === 1
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value >= 10_000 && (float) $value <= 100_000) {
            return (new DateTimeImmutable('1899-12-30'))
                ->modify('+'.(int) $value.' days')
                ->format('Y-m-d');
        }

        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeDocument(string $document): string
    {
        return mb_strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', $document), 'UTF-8');
    }
}
