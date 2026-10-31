<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Validation\ValidationException;

class InventoryUnitService
{
    /**
     * @return array{unit_conversion_id: int|null, unit_name: string, unit_factor: float}
     */
    public function snapshot(Item $item, ?int $conversionId, string $errorKey): array
    {
        if ($conversionId === null) {
            return [
                'unit_conversion_id' => null,
                'unit_name' => $item->base_unit ?: 'unidad',
                'unit_factor' => 1.0,
            ];
        }

        $conversion = $item->unitConversions()->whereKey($conversionId)->first();
        if (! $conversion) {
            throw ValidationException::withMessages([
                $errorKey => 'La presentación seleccionada no corresponde al producto.',
            ]);
        }

        return [
            'unit_conversion_id' => $conversion->id,
            'unit_name' => $conversion->name,
            'unit_factor' => (float) $conversion->factor,
        ];
    }
}
