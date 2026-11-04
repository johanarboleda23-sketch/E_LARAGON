<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class DianInvoiceXmlReader
{
    /**
     * @return array{
     *     invoice_number: string,
     *     cufe: string,
     *     invoice_date: ?string,
     *     due_date: ?string,
     *     supplier_name: ?string,
     *     supplier_document: ?string,
     *     customer_name: ?string,
     *     customer_document: ?string,
     *     total: ?string
     * }
     */
    public function read(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw ValidationException::withMessages([
                'xml_file' => 'No fue posible leer el archivo XML cargado.',
            ]);
        }

        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        if (! $xml instanceof \SimpleXMLElement || ! in_array($xml->getName(), ['Invoice', 'CreditNote', 'DebitNote'], true)) {
            throw ValidationException::withMessages([
                'xml_file' => 'El archivo no contiene una factura electrónica UBL válida.',
            ]);
        }

        $invoiceNumber = $this->firstValue($xml, '/*/*[local-name()="ID"]');
        $cufe = $this->readCufe($xml);

        if ($invoiceNumber === null || $cufe === null) {
            throw ValidationException::withMessages([
                'xml_file' => 'El XML no contiene el número de factura y un CUFE legible.',
            ]);
        }

        return [
            'invoice_number' => $invoiceNumber,
            'cufe' => $cufe,
            'invoice_date' => $this->firstValue($xml, '/*/*[local-name()="IssueDate"]'),
            'due_date' => $this->firstValue($xml, '/*/*[local-name()="DueDate"]'),
            'supplier_name' => $this->firstValue($xml, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyName"]/*[local-name()="Name"]')
                ?? $this->firstValue($xml, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyLegalEntity"]/*[local-name()="RegistrationName"]'),
            'supplier_document' => $this->firstValue($xml, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="CompanyID"]'),
            'customer_name' => $this->firstValue($xml, '//*[local-name()="AccountingCustomerParty"]//*[local-name()="PartyName"]/*[local-name()="Name"]')
                ?? $this->firstValue($xml, '//*[local-name()="AccountingCustomerParty"]//*[local-name()="PartyLegalEntity"]/*[local-name()="RegistrationName"]'),
            'customer_document' => $this->firstValue($xml, '//*[local-name()="AccountingCustomerParty"]//*[local-name()="CompanyID"]'),
            'total' => $this->firstValue($xml, '//*[local-name()="LegalMonetaryTotal"]/*[local-name()="PayableAmount"]'),
        ];
    }

    private function readCufe(\SimpleXMLElement $xml): ?string
    {
        foreach ($xml->xpath('//*[local-name()="UUID"]') ?: [] as $uuid) {
            $schemeName = mb_strtoupper((string) ($uuid->attributes()['schemeName'] ?? ''), 'UTF-8');
            $value = trim((string) $uuid);

            if (str_contains($schemeName, 'CUFE') && $value !== '') {
                return $value;
            }
        }

        foreach ($xml->xpath('//*[local-name()="UUID"]') ?: [] as $uuid) {
            $value = trim((string) $uuid);
            if (preg_match('/^[A-Fa-f0-9]{96}$/', $value) === 1) {
                return $value;
            }
        }

        return null;
    }

    private function firstValue(\SimpleXMLElement $xml, string $query): ?string
    {
        $nodes = $xml->xpath($query);
        if ($nodes === false || $nodes === []) {
            return null;
        }

        $value = trim((string) $nodes[0]);

        return $value !== '' ? $value : null;
    }
}
