<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ties_a_product_to_its_inventory_and_income_puc_accounts(): void
    {
        $this->authenticateWithCompany();
        $item = Item::create([
            'type' => 'producto',
            'name' => 'Producto de prueba',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => 10,
            'min_stock' => 1,
        ]);
        $inventoryAccount = $this->createAccount('1435-TEST', 'Inventario de prueba', 1);
        $incomeAccount = $this->createAccount('4135-TEST', 'Ingreso de prueba', 4, 'credit');

        $this->put(route('items.accounts', $item), [
            'inventory_account_id' => $inventoryAccount->id,
            'income_account_id' => $incomeAccount->id,
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame($inventoryAccount->id, $item->inventory_account_id);
        $this->assertSame($incomeAccount->id, $item->income_account_id);
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function createAccount(string $code, string $name, int $class, string $nature = 'debit'): ChartOfAccount
    {
        return ChartOfAccount::create([
            'code' => $code,
            'name' => $name,
            'class' => $class,
            'nature' => $nature,
            'allows_posting' => true,
            'active' => true,
        ]);
    }
}
