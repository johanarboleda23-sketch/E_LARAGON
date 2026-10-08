<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class CurrencyRate extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = [
        'rate_date' => 'date',
        'rate_to_cop' => 'decimal:4',
    ];

    /**
     * Monedas comunes sugeridas para Colombia. No restringe otras entradas libres.
     *
     * @var array<string, string>
     */
    public const SUGGESTED_CURRENCIES = [
        'USD' => 'Dólar estadounidense',
        'EUR' => 'Euro',
        'GBP' => 'Libra esterlina',
        'MXN' => 'Peso mexicano',
        'BRL' => 'Real brasileño',
        'CAD' => 'Dólar canadiense',
        'JPY' => 'Yen japonés',
        'CHF' => 'Franco suizo',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Devuelve la tasa más reciente registrada para cada moneda.
     *
     * @return Collection<string, self>
     */
    public static function latestPerCurrency(): Collection
    {
        return static::query()
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->get()
            ->unique('currency_code')
            ->keyBy('currency_code');
    }
}
