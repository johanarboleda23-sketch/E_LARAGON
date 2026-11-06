<?php

namespace App\Services;

class SalesRetentionCalculator
{
    /**
     * @return array{retention_base: float, retention_total: float}
     */
    public function calculate(string $conceptKey, float $subtotal, float $discountTotal, float $ivaTotal): array
    {
        $concept = config('colombia_withholdings.sales_concepts.'.$conceptKey);
        if (! is_array($concept)) {
            throw new \InvalidArgumentException('El concepto de retención de ventas no es válido.');
        }

        $retentionBase = $concept['base_on'] === 'iva'
            ? $ivaTotal
            : $subtotal - $discountTotal;
        $minimumBase = (float) $concept['base_uvt'] * (float) config('colombia_withholdings.uvt');
        $retentionBase = $retentionBase >= $minimumBase ? $retentionBase : 0;

        return [
            'retention_base' => $retentionBase,
            'retention_total' => round($retentionBase * (float) $concept['rate'], 2),
        ];
    }
}
