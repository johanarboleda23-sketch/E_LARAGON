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

    public function test_it_saves_a_product_base_unit_and_its_conversions(): void
    {
        $company = $this->authenticateWithCompany();

        $this->post(route('items.store'), [
            'type' => 'producto',
            'name' => 'Producto por unidad',
            'code' => 'SKU-UNIDADES',
            'sale_price' => 100,
            'purchase_price' => 50,
            'base_unit' => 'unidad',
            'stock' => 2.5,
            'min_stock' => 0.5,
            'unit_conversions' => [[
                'name' => 'caja',
                'factor' => 6,
                'sale_price' => 0,
            ]],
        ])->assertRedirect(route('items.index'));

        $item = Item::query()->where('code', 'SKU-UNIDADES')->firstOrFail();
        $conversion = $item->unitConversions()->firstOrFail();
        $this->assertSame($company->id, $item->company_id);
        $this->assertSame('unidad', $item->base_unit);
        $this->assertSame(2.5, $item->stock);
        $this->assertSame('caja', $conversion->name);
        $this->assertSame(6.0, (float) $conversion->factor);
        $this->assertSame(0.0, (float) $conversion->sale_price);
    }

    public function test_inventory_page_shows_unit_conversion_controls_and_configured_presentations(): void
    {
        $this->authenticateWithCompany();
        $item = Item::factory()->create([
            'name' => 'Producto con empaque',
            'base_unit' => 'unidad',
            'stock' => 12,
        ]);
        $item->unitConversions()->create([
            'name' => 'caja',
            'factor' => 6,
        ]);

        $this->get(route('items.index'))
            ->assertSee('Unidades, existencias y conversiones')
            ->assertSee('Agregar presentación y conversión')
            ->assertSee('name="base_unit"', false)
            ->assertSee('Presentaciones:', false)
            ->assertSee('caja (× 6)', false);
    }

    public function test_stock_adjustment_converts_selected_presentation_to_base_units(): void
    {
        $this->authenticateWithCompany();
        $item = Item::factory()->create([
            'stock' => 12,
            'base_unit' => 'unidad',
        ]);
        $conversion = $item->unitConversions()->create([
            'name' => 'caja',
            'factor' => 6,
        ]);

        $response = $this->post(route('items.stock', $item), [
            'movement_type' => 'salida',
            'unit_conversion_id' => $conversion->id,
            'quantity' => 2,
            'reason' => 'Ajuste por cajas',
        ]);

        $response->assertRedirect(route('items.index'));
        $this->assertSame(0.0, (float) $item->refresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'item_id' => $item->id,
            'type' => 'salida',
            'quantity' => 12,
            'stock_before' => 12,
            'stock_after' => 0,
            'reason' => 'Ajuste por cajas',
        ]);
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
            ['SKU-001', 'Producto actualizado', 125.5, 65, 12.5, 2.25],
            ['SKU-002', 'Producto nuevo', 200, '', 8.75, 3.5],
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
        $this->assertSame(12.5, (float) $existingItem->stock);
        $this->assertDatabaseHas('items', [
            'company_id' => $company->id,
            'code' => 'SKU-002',
            'name' => 'Producto nuevo',
            'purchase_price' => null,
            'stock' => 8.75,
            'min_stock' => 3.5,
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
