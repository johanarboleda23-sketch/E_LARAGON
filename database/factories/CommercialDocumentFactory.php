<?php

namespace Database\Factories;

use App\Models\CommercialDocument;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommercialDocument>
 */
class CommercialDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'document_type' => 'quotation',
            'consecutive' => fake()->unique()->bothify('COT-####'),
            'status' => 'draft',
            'third_party_name' => fake()->company(),
            'document_date' => now()->toDateString(),
            'subtotal' => 100,
            'discount_total' => 0,
            'iva_total' => 19,
            'total' => 119,
        ];
    }
}
