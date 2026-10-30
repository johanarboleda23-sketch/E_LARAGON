<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use App\Support\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
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

    public function test_it_imports_and_updates_products_from_an_excel_file(): void
    {
        $company = $this->authenticateWithCompany();
        $existingItem = Item::create([
            'type' => 'producto',
            'code' => 'SKU-001',
            'name' => 'Nombre anterior',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => 5,
            'min_stock' => 1,
        ]);
        $filePath = $this->createExcelFile([
            ['Código', 'Nombre', 'Precio de venta', 'Costo de compra', 'Stock', 'Stock mínimo'],
            ['SKU-001', 'Producto actualizado', 125.5, 65, 12, 2],
            ['SKU-002', 'Producto nuevo', 200, '', 8, 3],
        ], false);

        try {
            $response = $this->from(route('items.index'))->post(route('items.import'), [
                'file' => new UploadedFile($filePath, 'productos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ]);
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }

        $response->assertRedirect(route('items.index'))
            ->assertSessionHas('success', 'Importación completada: 1 creados y 1 actualizados.');
        $this->assertSame('Producto actualizado', $existingItem->refresh()->name);
        $this->assertDatabaseHas('items', [
            'company_id' => $company->id,
            'code' => 'SKU-002',
            'name' => 'Producto nuevo',
            'purchase_price' => null,
            'stock' => 8,
            'min_stock' => 3,
        ]);
    }

    public function test_it_rejects_duplicate_product_codes_without_importing_any_rows(): void
    {
        $this->authenticateWithCompany();
        $file = UploadedFile::fake()->createWithContent(
            'productos.csv',
            "\"Código\";\"Nombre\";\"Precio de venta\";\"Costo de compra\";\"Stock\";\"Stock mínimo\"\n".
                "\"SKU-001\";\"Producto uno\";\"100\";\"50\";\"5\";\"1\"\n".
                "\"SKU-001\";\"Producto repetido\";\"200\";\"100\";\"8\";\"2\"\n"
        );

        $response = $this->from(route('items.index'))->post(route('items.import'), ['file' => $file]);

        $response->assertRedirect(route('items.index'))
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('items', 0);
    }

    public function test_it_keeps_product_imports_isolated_to_the_active_company(): void
    {
        $company = $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $otherCompanyItem = Item::create([
            'company_id' => $otherCompany->id,
            'type' => 'producto',
            'code' => 'SKU-001',
            'name' => 'Producto de otra empresa',
            'sale_price' => 100,
            'stock' => 4,
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'productos.csv',
            "\"Código\";\"Nombre\";\"Precio de venta\";\"Costo de compra\";\"Stock\";\"Stock mínimo\"\n".
                "\"SKU-001\";\"Producto de mi empresa\";\"150\";\"75\";\"9\";\"2\"\n"
        );

        $response = $this->from(route('items.index'))->post(route('items.import'), ['file' => $file]);

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseHas('items', [
            'company_id' => $company->id,
            'code' => 'SKU-001',
            'name' => 'Producto de mi empresa',
        ]);
        $this->assertSame('Producto de otra empresa', $otherCompanyItem->refresh()->name);
        $this->assertSame(2, Item::withoutGlobalScopes()->where('code', 'SKU-001')->count());
    }

    public function test_it_exports_products_as_an_excel_download(): void
    {
        $this->authenticateWithCompany();
        Item::create([
            'type' => 'producto',
            'code' => 'SKU-001',
            'name' => 'Producto exportado',
            'sale_price' => 125.5,
            'purchase_price' => 65,
            'stock' => 12,
            'min_stock' => 2,
        ]);
        Item::create([
            'type' => 'servicio',
            'code' => 'SRV-001',
            'name' => 'Servicio excluido',
            'sale_price' => 90,
        ]);

        $response = $this->get(route('items.export'));

        $response->assertOk()->assertDownload('productos.xlsx');
        $filePath = $response->baseResponse->getFile()->getPathname();
        $rows = SpreadsheetReader::rows($filePath, 'xlsx');

        $this->assertSame(['Código', 'Nombre', 'Precio de venta', 'Costo de compra', 'Stock', 'Stock mínimo'], $rows[0]);
        $this->assertSame(['SKU-001', 'Producto exportado', '125.5', '65', '12', '2'], $rows[1]);
        $this->assertCount(2, $rows);

        if (is_file($filePath)) {
            unlink($filePath);
        }
    }

    private function createExcelFile(array $rows, bool $useInlineStrings = true): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'items-test-');
        $options = new Options;
        $options->SHOULD_USE_INLINE_STRINGS = $useInlineStrings;
        $writer = new Writer($options);
        $writer->openToFile($filePath);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $filePath;
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
