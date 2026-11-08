<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
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

    public function test_it_prevents_deleting_a_product_used_by_a_sales_invoice(): void
    {
        $this->authenticateWithCompany();
        $item = Item::factory()->create();
        $sale = Sale::create([
            'invoice_number' => 'FV-DELETE-001',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
        ]);
        $saleDetail = $sale->details()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $this->from(route('items.index'))
            ->delete(route('items.destroy', $item))
            ->assertRedirect(route('items.index'))
            ->assertSessionHasErrors([
                'item' => 'No se puede eliminar este registro porque ya tiene movimientos o documentos asociados. Conserva el registro para mantener el historial.',
            ]);

        $this->assertModelExists($item);
        $this->assertModelExists($saleDetail);
    }

    public function test_it_prevents_deleting_a_product_from_removing_its_purchase_history(): void
    {
        $this->authenticateWithCompany();
        $item = Item::factory()->create();
        $purchase = Purchase::create([
            'invoice_number' => 'COMP-DELETE-001',
            'provider' => 'Proveedor de prueba',
            'purchase_date' => now()->toDateString(),
        ]);
        $purchaseDetail = $purchase->details()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'cost_price' => 50,
            'iva_percentage' => 0,
            'iva_value' => 0,
            'utility_percentage' => 0,
            'calculated_sale_price' => 50,
        ]);

        $this->from(route('items.index'))
            ->delete(route('items.destroy', $item))
            ->assertRedirect(route('items.index'))
            ->assertSessionHasErrors([
                'item' => 'No se puede eliminar este registro porque ya tiene movimientos o documentos asociados. Conserva el registro para mantener el historial.',
            ]);

        $this->assertModelExists($item);
        $this->assertModelExists($purchaseDetail);
    }

    public function test_it_deletes_a_product_without_inventory_history(): void
    {
        $this->authenticateWithCompany();
        $item = Item::factory()->create(['stock' => 0]);

        $this->delete(route('items.destroy', $item))
            ->assertRedirect(route('items.index'))
            ->assertSessionHas('success', 'Registro eliminado con éxito.');

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_it_bulk_deletes_selected_products_without_history_and_leaves_unselected_products(): void
    {
        $company = $this->authenticateWithCompany();
        $firstItem = Item::factory()->create(['company_id' => $company->id, 'name' => 'Extintor de muestra']);
        $secondItem = Item::factory()->create(['company_id' => $company->id, 'name' => 'Teléfono de muestra']);
        $unselectedItem = Item::factory()->create(['company_id' => $company->id, 'name' => 'Producto conservado']);

        $this->from(route('items.index'))
            ->delete(route('items.bulk-destroy'), [
                'items' => [$firstItem->id, $secondItem->id],
            ])
            ->assertRedirect(route('items.index'))
            ->assertSessionHas('success', 'Se eliminaron 2 registros.');

        $this->assertDatabaseMissing('items', ['id' => $firstItem->id]);
        $this->assertDatabaseMissing('items', ['id' => $secondItem->id]);
        $this->assertModelExists($unselectedItem);
    }

    public function test_it_preserves_selected_products_with_history_while_deleting_eligible_products(): void
    {
        $this->authenticateWithCompany();
        $historicalItem = Item::factory()->create(['name' => 'Producto con venta']);
        $eligibleItem = Item::factory()->create(['name' => 'Producto sin movimientos']);
        $sale = Sale::create([
            'invoice_number' => 'FV-BULK-DELETE-001',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
        ]);
        $sale->details()->create([
            'item_id' => $historicalItem->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $this->delete(route('items.bulk-destroy'), [
            'items' => [$historicalItem->id, $eligibleItem->id],
        ])
            ->assertRedirect(route('items.index'))
            ->assertSessionHas('warning', 'Se eliminaron 1 registros. Se conservaron 1 porque tienen movimientos o documentos asociados.');

        $this->assertModelExists($historicalItem);
        $this->assertDatabaseMissing('items', ['id' => $eligibleItem->id]);
    }

    public function test_it_rejects_bulk_deletion_of_products_from_another_company(): void
    {
        $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $otherCompanyItem = Item::factory()->create(['company_id' => $otherCompany->id]);

        $this->from(route('items.index'))
            ->delete(route('items.bulk-destroy'), [
                'items' => [$otherCompanyItem->id],
            ])
            ->assertRedirect(route('items.index'))
            ->assertSessionHasErrors('items');

        $this->assertModelExists($otherCompanyItem);
    }

    public function test_it_requires_at_least_one_selected_product_for_bulk_deletion(): void
    {
        $this->authenticateWithCompany();

        $this->from(route('items.index'))
            ->delete(route('items.bulk-destroy'), ['items' => []])
            ->assertRedirect(route('items.index'))
            ->assertSessionHasErrors('items');
    }

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
            ], [
                'name' => 'media caja',
                'factor' => '1/2',
            ]],
        ])->assertRedirect(route('items.index'));

        $item = Item::query()->where('code', 'SKU-UNIDADES')->firstOrFail();
        $conversion = $item->unitConversions()->firstOrFail();
        $fractionalConversion = $item->unitConversions()->where('name', 'media caja')->firstOrFail();
        $this->assertSame($company->id, $item->company_id);
        $this->assertSame('unidad', $item->base_unit);
        $this->assertSame(2.5, $item->stock);
        $this->assertSame('caja', $conversion->name);
        $this->assertSame(6.0, (float) $conversion->factor);
        $this->assertSame(0.0, (float) $conversion->sale_price);
        $this->assertSame(0.5, (float) $fractionalConversion->factor);
    }

    public function test_inventory_page_shows_unit_conversion_controls_and_configured_presentations(): void
    {
        $this->authenticateWithCompany();
        $item = Item::factory()->create([
            'name' => 'Producto con empaque',
            'code' => 'SKU-EMP-001',
            'base_unit' => 'unidad',
            'stock' => 12,
        ]);
        $item->unitConversions()->create([
            'name' => 'caja',
            'factor' => 6,
        ]);

        $this->get(route('items.index'))
            ->assertSee('Unidades, existencias y conversiones')
            ->assertSee('Seleccionar todos los registros visibles')
            ->assertSee('Eliminar productos seleccionados')
            ->assertSee('Agregar presentación y conversión')
            ->assertSee('Código')
            ->assertSee('SKU-EMP-001')
            ->assertDontSee('<td class="px-6 py-4 text-sm font-semibold text-pink-600">Producto</td>', false)
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
