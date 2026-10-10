<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'income_account_id');
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    /**
     * Recalcula el costo promedio ponderado del producto a partir de todas sus compras no
     * eliminadas. Se recalcula "desde cero" (en vez de ajustar incrementalmente) para que las
     * ediciones y eliminaciones de facturas de compra siempre dejen el costo correcto, sin
     * necesidad de reconstruir un historial de movimientos.
     */
    public function recalculateAverageCost(): void
    {
        $totals = $this->purchaseDetails()
            ->where('purchase_line_type', 'producto')
            ->whereHas('purchase', fn ($query) => $query->whereNull('deleted_at'))
            ->selectRaw('SUM(quantity) as total_quantity, SUM(quantity * cost_price) as total_cost')
            ->first();

        $totalQuantity = (float) ($totals->total_quantity ?? 0);
        $totalCost = (float) ($totals->total_cost ?? 0);

        $this->update([
            'average_cost' => $totalQuantity > 0 ? round($totalCost / $totalQuantity, 4) : null,
        ]);
    }

    /**
     * Precio de venta sugerido a partir del costo promedio y el margen deseado (margen sobre
     * costo: precio = costo * (1 + margen / 100)).
     */
    public function suggestedSalePrice(): ?float
    {
        if (! $this->average_cost || $this->desired_margin_percentage === null) {
            return null;
        }

        return round((float) $this->average_cost * (1 + (float) $this->desired_margin_percentage / 100), 2);
    }

    /**
     * Margen real actual entre el precio de venta vigente y el costo promedio.
     */
    public function currentMarginPercentage(): ?float
    {
        if (! $this->average_cost || (float) $this->average_cost <= 0) {
            return null;
        }

        return round((((float) $this->sale_price - (float) $this->average_cost) / (float) $this->average_cost) * 100, 2);
    }
}
