<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class RutPdfExtractionService
{
    /**
     * @return array{name: ?string, document: ?string, type: ?string, address: ?string, city: ?string, email: ?string}
     */
    public function extract(UploadedFile $rutPdf): array
    {
        $config = new Config;
        $config->setDecodeMemoryLimit(50 * 1024 * 1024);

        try {
            $text = (new Parser([], $config))->parseFile($rutPdf->getPathname())->getText();
        } catch (\Exception) {
            throw ValidationException::withMessages([
                'rut_pdf' => 'No fue posible leer el PDF. Verifica que no esté protegido o dañado.',
            ]);
        }

        $companyName = $this->fieldValue($text, '35\.\s*Raz[oó]n\s+social', '36\.\s*Nombre\s+comercial');
        $personalName = implode(' ', array_filter([
            $this->fieldValue($text, '31\.\s*Primer\s+apellido', '32\.\s*Segundo\s+apellido'),
            $this->fieldValue($text, '32\.\s*Segundo\s+apellido', '33\.\s*Primer\s+nombre'),
            $this->fieldValue($text, '33\.\s*Primer\s+nombre', '34\.\s*Otros\s+nombres'),
            $this->fieldValue($text, '34\.\s*Otros\s+nombres', '35\.\s*Raz[oó]n\s+social'),
        ]));
        $document = $this->fieldValue($text, '5\.\s*N[uú]mero\s+de\s+Identificaci[oó]n\s+Tributaria\s*\(NIT\)', '6\.\s*DV');
        $city = $this->fieldValue($text, '40\.\s*Ciudad\s*/\s*Municipio', '41\.\s*Direcci[oó]n\s+principal');

        if ($city !== null) {
            $city = preg_replace('/^\s*\d{5,}\s*/u', '', $city);
        }

        return [
            'name' => $companyName ?? ($personalName !== '' ? $personalName : null),
            'document' => $document !== null ? preg_replace('/\D/u', '', $document) : null,
            'type' => $companyName !== null ? 'juridica' : ($personalName !== '' ? 'natural' : null),
            'address' => $this->fieldValue($text, '41\.\s*Direcci[oó]n\s+principal', '42\.\s*Correo\s+electr[oó]nico'),
            'city' => $city !== '' ? $city : null,
            'email' => $this->fieldValue($text, '42\.\s*Correo\s+electr[oó]nico', '43\.'),
        ];
    }

    private function fieldValue(string $text, string $labelPattern, string $nextLabelPattern): ?string
    {
        $pattern = '~'.$labelPattern.'\s*(.*?)\s*(?='.$nextLabelPattern.')~isu';

        if (! preg_match($pattern, $text, $matches)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $matches[1]) ?? '');

        return $value !== '' ? $value : null;
    }
}
