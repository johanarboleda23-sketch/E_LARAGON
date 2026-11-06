<?php

namespace App\Services;

use App\Models\CommercialDocument;
use App\Models\Company;
use App\Models\FactusCredential;
use App\Models\PayrollLine;
use App\Models\Sale;
use App\Models\SupportDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FactusService
{
    public function resolveCredential(?int $companyId): ?FactusCredential
    {
        if (! $companyId) {
            return null;
        }

        return FactusCredential::query()->where('company_id', $companyId)->where('active', true)->first();
    }

    public function sendSaleInvoice(Sale $sale, ?int $companyId, ?string &$skipReason = null): bool
    {
        $credential = $this->resolveCredential($companyId);
        if (! $credential) {
            $skipReason = 'Configura las credenciales de Factus en Administración para enviar facturas a la DIAN.';

            return false;
        }

        try {
            $token = $this->getAccessToken($credential);
        } catch (\Throwable $e) {
            $skipReason = 'No se pudo autenticar con Factus: '.$e->getMessage();

            return false;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl($credential).'/v2/bills/validate', $this->invoicePayload($sale, $credential));

        $sale->forceFill([
            'factus_response' => $response->json(),
            'factus_sent_at' => now(),
        ]);

        if (! $response->successful()) {
            $sale->forceFill(['factus_status' => 'error'])->save();
            $skipReason = 'Factus rechazó la factura: '.($response->json('message') ?? $response->body());

            return false;
        }

        $sale->forceFill([
            'factus_status' => 'enviada',
            'factus_number' => $response->json('data.bill.number'),
            'factus_cufe' => $response->json('data.bill.cufe'),
        ])->save();

        return true;
    }

    public function sendCustomerCreditNote(CommercialDocument $document, ?int $companyId, ?string &$skipReason = null): bool
    {
        if ($document->document_type !== 'customer_credit_note') {
            $skipReason = 'Solo se pueden enviar a la DIAN notas crédito de clientes.';

            return false;
        }

        if ($document->status !== 'draft' || $document->dian_status === 'sent') {
            $skipReason = 'La nota no está disponible para envío a la DIAN.';

            return false;
        }

        $credential = $this->resolveCredential($companyId);
        if (! $credential) {
            $skipReason = 'Configura las credenciales de Factus en Administración para enviar notas crédito a la DIAN.';

            return false;
        }

        $document->loadMissing(['lines.item', 'referenceSale']);
        if (! $document->referenceSale?->factus_number) {
            $skipReason = 'La factura origen debe estar emitida electrónicamente por Factus antes de crear su nota crédito.';

            return false;
        }

        if (! $document->correction_concept_code) {
            $skipReason = 'Selecciona el concepto de corrección DIAN antes de enviar la nota.';

            return false;
        }

        try {
            $token = $this->getAccessToken($credential);
        } catch (\Throwable $e) {
            $skipReason = 'No se pudo autenticar con Factus: '.$e->getMessage();

            return false;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(30)
                ->post($this->baseUrl($credential).'/v2/credit-notes/validate', $this->creditNotePayload($document, $credential));
        } catch (ConnectionException $e) {
            $document->forceFill([
                'dian_status' => 'error',
                'dian_response' => ['message' => $e->getMessage()],
                'dian_sent_at' => now(),
            ])->save();
            $skipReason = 'No se pudo conectar con Factus para enviar la nota crédito.';

            return false;
        }

        $responseBody = $response->json();
        $document->forceFill([
            'dian_response' => is_array($responseBody) ? $responseBody : ['body' => $response->body()],
            'dian_sent_at' => now(),
        ]);

        if (! $response->successful()) {
            $document->forceFill(['dian_status' => 'error'])->save();
            $skipReason = 'Factus rechazó la nota crédito: '.($response->json('message') ?? $response->body());

            return false;
        }

        $creditNoteData = $response->json('data.credit_note') ?? $response->json('data.creditNote') ?? [];
        $document->forceFill([
            'dian_status' => 'sent',
            'dian_number' => is_array($creditNoteData) ? ($creditNoteData['number'] ?? null) : null,
            'dian_cude' => is_array($creditNoteData) ? ($creditNoteData['cude'] ?? null) : null,
        ])->save();

        return true;
    }

    public function sendSupportDocumentInvoice(SupportDocument $document, ?int $companyId, ?string &$skipReason = null): bool
    {
        $credential = $this->resolveCredential($companyId);
        if (! $credential) {
            $skipReason = 'Configura las credenciales de Factus en Administración para enviar el documento soporte a la DIAN.';

            return false;
        }

        try {
            $token = $this->getAccessToken($credential);
        } catch (\Throwable $e) {
            $skipReason = 'No se pudo autenticar con Factus: '.$e->getMessage();

            return false;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl($credential).'/v2/support-documents/validate', $this->supportDocumentPayload($document, $companyId));

        $document->forceFill([
            'factus_response' => $response->json(),
            'factus_sent_at' => now(),
        ]);

        if (! $response->successful()) {
            $document->forceFill(['factus_status' => 'error'])->save();
            $skipReason = 'Factus rechazó el documento soporte: '.($response->json('message') ?? $response->body());

            return false;
        }

        $document->forceFill([
            'factus_status' => 'enviada',
            'factus_number' => $response->json('data.bill.number'),
            'factus_cufe' => $response->json('data.bill.cufe'),
        ])->save();

        return true;
    }

    public function sendPayrollInvoice(PayrollLine $line, ?int $companyId, ?string &$skipReason = null): bool
    {
        $credential = $this->resolveCredential($companyId);
        if (! $credential) {
            $skipReason = 'Configura las credenciales de Factus en Administración para enviar la nómina a la DIAN.';

            return false;
        }

        if (! $credential->payroll_numbering_range_id) {
            $skipReason = 'Configura el rango de numeración de nómina de Factus en Administración.';

            return false;
        }

        try {
            $token = $this->getAccessToken($credential);
        } catch (\Throwable $e) {
            $skipReason = 'No se pudo autenticar con Factus: '.$e->getMessage();

            return false;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl($credential).'/v2/payrolls', $this->payrollPayload($line, $credential));

        $line->forceFill([
            'factus_response' => $response->json(),
            'factus_sent_at' => now(),
        ]);

        if (! $response->successful()) {
            $line->forceFill(['factus_status' => 'error'])->save();
            $skipReason = 'Factus rechazó la nómina: '.($response->json('message') ?? $response->body());

            return false;
        }

        $line->forceFill([
            'factus_status' => 'enviada',
            'factus_number' => $response->json('data.bill.number'),
            'factus_cufe' => $response->json('data.bill.cufe'),
        ])->save();

        return true;
    }

    private function getAccessToken(FactusCredential $credential): string
    {
        return Cache::remember(
            'factus_access_token_'.$credential->company_id,
            now()->addMinutes(55),
            function () use ($credential): string {
                $response = Http::asForm()
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->post($this->baseUrl($credential).'/oauth/token', [
                        'grant_type' => 'password',
                        'client_id' => $credential->client_id,
                        'client_secret' => $credential->client_secret,
                        'username' => $credential->username,
                        'password' => $credential->password,
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException($response->json('message') ?? 'Credenciales inválidas.');
                }

                return $response->json('access_token');
            }
        );
    }

    private function baseUrl(FactusCredential $credential): string
    {
        return config('factus.base_urls.'.$credential->environment, config('factus.base_urls.sandbox'));
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Sale $sale, FactusCredential $credential): array
    {
        $defaults = config('factus.defaults');
        $document = (string) ($sale->customer_document ?? '');
        $isCompany = strlen(preg_replace('/\D/', '', $document)) >= 9;

        $sale->loadMissing('details.item');

        return [
            'reference_code' => $sale->invoice_number,
            'document' => $defaults['document'],
            'numbering_range_id' => $credential->invoice_numbering_range_id,
            'operation_type' => $defaults['operation_type'],
            'payment_details' => [[
                'payment_form' => $defaults['payment_form'],
                'payment_method_code' => $defaults['payment_method_code'],
                'reference_code' => $sale->invoice_number,
                'amount' => number_format((float) $sale->total, 2, '.', ''),
            ]],
            'customer' => [
                'identification_document_code' => $isCompany ? $defaults['nit_identification_document_code'] : $defaults['identification_document_code'],
                'identification' => $document !== '' ? $document : '222222222222',
                'company' => $sale->customer_name,
                'trade_name' => $sale->customer_name,
                'email' => $sale->customer_email,
                'legal_organization_code' => $isCompany ? $defaults['nit_legal_organization_code'] : $defaults['legal_organization_code'],
                'tribute_code' => $defaults['tribute_code'],
                'country_code' => $defaults['country_code'],
                'municipality_code' => $defaults['municipality_code'],
            ],
            'items' => $sale->details->map(fn ($detail) => [
                'code_reference' => (string) $detail->item_id,
                'name' => $detail->item->name,
                'quantity' => (string) $detail->quantity,
                'discount_rate' => number_format((float) $detail->discount_percentage, 2, '.', ''),
                'price' => number_format((float) $detail->unit_price, 2, '.', ''),
                'unit_measure_code' => $defaults['unit_measure_code'],
                'standard_code' => $defaults['standard_code'],
                'taxes' => [[
                    'code' => $defaults['tax_code'],
                    'rate' => number_format((float) $detail->iva_percentage, 2, '.', ''),
                ]],
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creditNotePayload(CommercialDocument $document, FactusCredential $credential): array
    {
        $defaults = config('factus.defaults');
        $sale = $document->referenceSale;
        $customerDocument = (string) ($sale->customer_document ?? '');
        $isCompany = strlen(preg_replace('/\D/', '', $customerDocument)) >= 9;
        $notes = trim((string) $document->notes);
        $payload = [
            'reference_code' => $document->consecutive,
            'correction_concept_code' => $document->correction_concept_code,
            'bill_number' => $sale->factus_number,
            'customer' => [
                'identification_document_code' => $isCompany ? $defaults['nit_identification_document_code'] : $defaults['identification_document_code'],
                'identification' => $customerDocument !== '' ? $customerDocument : '222222222222',
                'company' => $sale->customer_name,
                'trade_name' => $sale->customer_name,
                'email' => $sale->customer_email,
                'legal_organization_code' => $isCompany ? $defaults['nit_legal_organization_code'] : $defaults['legal_organization_code'],
                'tribute_code' => $defaults['tribute_code'],
                'country_code' => $defaults['country_code'],
                'municipality_code' => $defaults['municipality_code'],
            ],
            'payment_details' => [[
                'payment_form' => $defaults['payment_form'],
                'payment_method_code' => $defaults['payment_method_code'],
                'reference_code' => $document->consecutive,
                'amount' => number_format((float) $document->total, 2, '.', ''),
            ]],
            'items' => $document->lines->map(fn ($line) => [
                'code_reference' => (string) $line->item_id,
                'name' => $line->description,
                'quantity' => number_format((float) $line->quantity, 4, '.', ''),
                'discount_rate' => number_format((float) $line->discount_percentage, 2, '.', ''),
                'price' => number_format((float) $line->unit_price, 2, '.', ''),
                'unit_measure_code' => $defaults['unit_measure_code'],
                'standard_code' => $defaults['standard_code'],
                'taxes' => (float) $line->iva_percentage > 0 ? [[
                    'code' => $defaults['tax_code'],
                    'rate' => number_format((float) $line->iva_percentage, 2, '.', ''),
                ]] : [],
            ])->values()->all(),
        ];

        if ($credential->credit_note_numbering_range_id) {
            $payload['numbering_range_id'] = $credential->credit_note_numbering_range_id;
        }

        if ($notes !== '') {
            $payload['observation'] = mb_substr($notes, 0, 500);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function supportDocumentPayload(SupportDocument $document, ?int $companyId): array
    {
        $defaults = config('factus.defaults');
        $document->loadMissing('supplier');
        $company = Company::query()->find($companyId);
        $providerDocument = (string) ($document->supplier->document ?? '');
        $isCompanyProvider = strlen(preg_replace('/\D/', '', $providerDocument)) >= 9;

        return [
            'reference_code' => $document->consecutive,
            'observation' => $document->concept,
            'payment_details' => [[
                'payment_form' => $defaults['payment_form'],
                'payment_method_code' => $defaults['payment_method_code'],
                'reference_code' => $document->consecutive,
                'amount' => number_format((float) $document->total, 2, '.', ''),
            ]],
            'establishment' => [
                'name' => $company->name ?? 'Establecimiento principal',
                'address' => 'N/A',
                'phone_number' => $company->phone ?? '0000000000',
                'email' => $company->email ?? 'contacto@empresa.com',
                'municipality_code' => $defaults['municipality_code'],
            ],
            'provider' => [
                'identification_document_code' => $isCompanyProvider ? $defaults['nit_identification_document_code'] : $defaults['identification_document_code'],
                'identification' => $providerDocument !== '' ? $providerDocument : '222222222222',
                'legal_organization_code' => $isCompanyProvider ? $defaults['nit_legal_organization_code'] : $defaults['legal_organization_code'],
                'names' => $document->supplier->name,
                'company' => $document->supplier->name,
                'address' => 'N/A',
                'country_code' => $defaults['country_code'],
                'municipality_code' => $defaults['municipality_code'],
            ],
            'items' => [[
                'code_reference' => 'DS-'.$document->id,
                'name' => $document->concept,
                'quantity' => '1.00',
                'discount_rate' => '0.00',
                'price' => number_format((float) $document->subtotal, 2, '.', ''),
                'unit_measure_code' => $defaults['unit_measure_code'],
                'standard_code' => $defaults['standard_code'],
                'withholding_taxes' => (float) $document->retention_total > 0 ? [[
                    'code' => '06',
                    'rate' => $document->subtotal > 0 ? number_format(((float) $document->retention_total / (float) $document->subtotal) * 100, 2, '.', '') : '0.00',
                ]] : [],
                'taxes' => (float) $document->iva_total > 0 ? [[
                    'code' => $defaults['tax_code'],
                    'rate' => $document->subtotal > 0 ? number_format(((float) $document->iva_total / (float) $document->subtotal) * 100, 2, '.', '') : '0.00',
                ]] : [],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollPayload(PayrollLine $line, FactusCredential $credential): array
    {
        $defaults = config('factus.defaults');
        $line->loadMissing('employee', 'payrollRun');
        $run = $line->payrollRun;
        [$year, $month] = explode('-', $run->period);

        $employeeDocument = (string) ($line->employee->document ?? '');
        $nameParts = preg_split('/\s+/', trim($line->employee->name ?? ''));
        $firstName = $nameParts[0] ?? $line->employee->name;
        $firstSurname = $nameParts[1] ?? ($nameParts[0] ?? '');

        return [
            'reference_code' => 'NO-'.$run->period.'-'.$line->id,
            'observation' => 'Nómina período '.$run->period,
            'numbering_range_id' => $credential->payroll_numbering_range_id,
            'settlement_period' => [
                'month' => (string) (int) $month,
                'year' => $year,
                'payroll_period_code' => $defaults['payroll_period_code'],
            ],
            'payment' => [
                'payment_method_code' => $defaults['payroll_payment_method_code'],
                'payment_date' => $run->payment_date->toDateString(),
            ],
            'worker' => [
                'identification_document_code' => $defaults['identification_document_code'],
                'identification_number' => $employeeDocument !== '' ? $employeeDocument : '0000000000',
                'first_name' => $firstName,
                'first_surname' => $firstSurname,
                'address' => 'N/A',
                'country_code' => $defaults['country_code'],
                'municipality_code' => $defaults['municipality_code'],
                'has_integral_salary' => false,
                'has_high_risk' => false,
                'worker_subtype' => $defaults['worker_subtype_code'],
                'contract_type' => $defaults['contract_type_code'],
                'worker_type_code' => $defaults['worker_type_code'],
                'salary' => number_format((float) $line->salary, 2, '.', ''),
                'entry_date' => $run->payment_date->toDateString(),
                'days_worked' => '30.00',
            ],
            'accruals' => [
                'suel' => ['amount' => number_format((float) $line->salary, 2, '.', '')],
            ],
            'deductions' => array_filter([
                'salu' => (float) $line->health_employee > 0 ? ['amount' => number_format((float) $line->health_employee, 2, '.', ''), 'percentage' => '4'] : null,
                'pens' => (float) $line->pension_employee > 0 ? ['amount' => number_format((float) $line->pension_employee, 2, '.', ''), 'percentage' => '4'] : null,
            ]),
        ];
    }
}
