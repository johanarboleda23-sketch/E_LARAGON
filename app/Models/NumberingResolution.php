<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\NumberingResolutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumberingResolution extends Model
{
    /** @use HasFactory<NumberingResolutionFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'document_type',
        'prefix',
        'resolution_number',
        'resolution_date',
        'valid_from',
        'valid_until',
        'range_from',
        'range_to',
        'next_number',
        'active',
    ];

    protected $casts = [
        'resolution_date' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'active' => 'boolean',
    ];

    /**
     * Allocates and returns the next formatted consecutive number for a document type,
     * or null when the company has no active resolution configured for it (manual mode).
     *
     * @throws \RuntimeException when the active resolution is expired or exhausted.
     */
    public static function allocateNext(string $documentType): ?string
    {
        return self::query()
            ->where('document_type', $documentType)
            ->where('active', true)
            ->lockForUpdate()
            ->first()
            ?->consumeNext();
    }

    /**
     * Returns the next consecutive that would be assigned, without consuming it.
     * Used to display the real number a document will receive before saving it.
     */
    public static function peekNext(string $documentType): ?string
    {
        $resolution = self::query()->where('document_type', $documentType)->where('active', true)->first();

        if (! $resolution) {
            return null;
        }

        return trim(($resolution->prefix ? $resolution->prefix.'-' : '').$resolution->next_number);
    }

    private function consumeNext(): string
    {
        $today = now()->toDateString();

        if ($this->valid_until && $this->valid_until->toDateString() < $today) {
            throw new \RuntimeException("La resolución de numeración {$this->resolution_number} está vencida.");
        }

        if ($this->next_number > $this->range_to) {
            throw new \RuntimeException("La resolución de numeración {$this->resolution_number} agotó su rango autorizado.");
        }

        $number = $this->next_number;
        $this->increment('next_number');

        return trim(($this->prefix ? $this->prefix.'-' : '').$number);
    }
}
