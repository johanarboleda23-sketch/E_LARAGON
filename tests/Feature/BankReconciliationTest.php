<?php

namespace Tests\Feature;

use App\Models\BankStatement;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_and_reconciles_a_bank_movement(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $csv = "fecha;referencia;descripcion;tipo;importe\n2026-10-01;TRX-001;Pago proveedor;debit;250000\n";
        $this->post(route('bank-reconciliation.import'), [
            'file' => UploadedFile::fake()->createWithContent('extracto.csv', $csv),
        ])->assertRedirect();

        $statement = BankStatement::query()->firstOrFail();
        $this->assertSame('debit', $statement->transaction_type);
        $this->assertSame('pending', $statement->status);

        $this->post(route('bank-reconciliation.reconcile', $statement), [
            'matched_type' => 'manual',
            'notes' => 'Revisado contra extracto bancario.',
        ])->assertRedirect();

        $this->assertSame('reconciled', $statement->fresh()->status);
        $this->assertNotNull($statement->fresh()->reconciled_at);
    }

    public function test_it_imports_the_three_column_excel_export_shape_as_credit_or_debit(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $csv = "Fecha;Descripción;Valor\n46292.208333333328;TRANSFERENCIA CTA SUC VIRTUAL;-50000\n46291.208333333328;CONSIGNACION CORRESPONSAL CB;425000\n";
        $this->post(route('bank-reconciliation.import'), [
            'file' => UploadedFile::fake()->createWithContent('joh.csv', $csv),
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_statements', [
            'description' => 'TRANSFERENCIA CTA SUC VIRTUAL',
            'transaction_type' => 'debit',
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('bank_statements', [
            'description' => 'CONSIGNACION CORRESPONSAL CB',
            'transaction_type' => 'credit',
            'amount' => 425000,
        ]);
    }
}
