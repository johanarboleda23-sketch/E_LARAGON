<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Support\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ItemController extends Controller
{
    private const PRODUCT_HEADERS = ['Código', 'Nombre', 'Precio de venta', 'Costo de compra', 'Stock', 'Stock mínimo'];

    // Muestra la lista de productos/servicios y la opción Modal
    public function index()
    {
        $items = Item::query()
            ->with(['inventoryAccount', 'incomeAccount'])
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(request('type'), fn ($query, $type) => $query->where('type', $type))
            ->when(request('low_stock'), fn ($query) => $query->whereColumn('stock', '<=', 'min_stock'))
            ->latest()
            ->get();
        $postingAccounts = ChartOfAccount::query()->where('active', true)->where('allows_posting', true)->orderBy('code')->get(['id', 'code', 'name']);

        return view('items.index', compact('items', 'postingAccounts'));
    }

    // Muestra el formulario en una página completa
    public function create()
    {
        return view('items.create');
    }

    // Guarda el producto o servicio en Laragon
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:producto,servicio',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
        ]);

        Item::create($this->itemAttributes($data));

        return redirect()->route('items.index')->with('success', 'Registro creado con éxito.');
    }

    public function edit(Item $item)
    {
        return view('items.edit', compact('item'));
    }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'type' => 'required|in:producto,servicio',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
        ]);

        $item->update($this->itemAttributes($data));

        return redirect()->route('items.index')->with('success', 'Registro actualizado con éxito.');
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()->route('items.index')->with('success', 'Registro eliminado con éxito.');
    }

    public function updateAccounts(Request $request, Item $item)
    {
        $data = $request->validate([
            'inventory_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'income_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $item->update($data);

        return back()->with('success', 'Cuentas PUC de "'.$item->name.'" actualizadas. La contabilización automática las usará a partir de ahora.');
    }

    public function adjustStock(Request $request, Item $item)
    {
        abort_if($item->type !== 'producto', 422, 'Los servicios no manejan existencias.');

        $data = $request->validate([
            'movement_type' => 'required|in:entrada,salida',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $quantity = (int) $data['quantity'];
        $stockBefore = $item->stock;
        $stockAfter = $data['movement_type'] === 'entrada'
            ? $stockBefore + $quantity
            : $stockBefore - $quantity;

        abort_if($stockAfter < 0, 422, 'La salida supera el stock disponible.');

        $item->update(['stock' => $stockAfter]);
        InventoryMovement::create([
            'item_id' => $item->id,
            'type' => $data['movement_type'],
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $data['reason'] ?? 'Movimiento manual',
        ]);

        return redirect()->route('items.index')->with('success', 'Movimiento aplicado correctamente.');
    }

    public function template(): BinaryFileResponse
    {
        return $this->downloadSpreadsheet('plantilla-productos.xlsx', [
            self::PRODUCT_HEADERS,
        ]);
    }

    public function export(): BinaryFileResponse
    {
        $rows = (function (): \Generator {
            yield self::PRODUCT_HEADERS;

            foreach (Item::query()->where('type', 'producto')->orderBy('name')->cursor() as $item) {
                yield [
                    $item->code ?? '',
                    $item->name,
                    $item->sale_price,
                    $item->purchase_price ?? '',
                    $item->stock,
                    $item->min_stock,
                ];
            }
        })();

        return $this->downloadSpreadsheet('productos.xlsx', $rows);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
        ]);

        $filePath = $data['file']->getRealPath();
        if ($filePath === false) {
            throw new \RuntimeException('No fue posible leer el archivo cargado.');
        }

        $rows = SpreadsheetReader::rows($filePath, $data['file']->getClientOriginalExtension());
        $headers = array_map(static fn ($header): string => trim((string) $header), array_shift($rows) ?? []);
        if (isset($headers[0])) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        }

        if ($headers !== self::PRODUCT_HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'La primera fila debe tener los encabezados de la plantilla de productos.',
            ]);
        }

        $rows = array_values(array_filter($rows, static function (array $row): bool {
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }
            }

            return false;
        }));

        if (count($rows) > 500) {
            throw ValidationException::withMessages([
                'file' => 'La plantilla admite máximo 500 productos por carga.',
            ]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'El archivo no contiene productos para importar.',
            ]);
        }

        $records = [];
        $seenCodes = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $purchasePrice = trim((string) ($row[3] ?? ''));
            $stock = trim((string) ($row[4] ?? ''));
            $minimumStock = trim((string) ($row[5] ?? ''));
            $input = [
                'code' => trim((string) ($row[0] ?? '')),
                'name' => trim((string) ($row[1] ?? '')),
                'sale_price' => trim((string) ($row[2] ?? '')),
                'purchase_price' => $purchasePrice === '' ? null : $purchasePrice,
                'stock' => $stock === '' ? null : $stock,
                'min_stock' => $minimumStock === '' ? null : $minimumStock,
            ];
            $validator = Validator::make($input, [
                'code' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'sale_price' => ['required', 'numeric', 'min:0'],
                'purchase_price' => ['nullable', 'numeric', 'min:0'],
                'stock' => ['nullable', 'integer', 'min:0'],
                'min_stock' => ['nullable', 'integer', 'min:0'],
            ], [], [
                'code' => 'código',
                'name' => 'nombre',
                'sale_price' => 'precio de venta',
                'purchase_price' => 'costo de compra',
                'stock' => 'stock',
                'min_stock' => 'stock mínimo',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = 'Fila '.($index + 2).': '.$message;
                }

                continue;
            }

            $record = $validator->validated();
            $normalizedCode = mb_strtolower($record['code'], 'UTF-8');
            if (isset($seenCodes[$normalizedCode])) {
                $errors[] = 'Fila '.($index + 2).': el código "'.$record['code'].'" está repetido en el archivo.';

                continue;
            }

            $seenCodes[$normalizedCode] = true;
            $records[] = [
                ...$record,
                'stock' => $record['stock'] ?? 0,
                'min_stock' => $record['min_stock'] ?? 0,
                'normalized_code' => $normalizedCode,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => implode(' ', array_slice($errors, 0, 20)).(count($errors) > 20 ? ' Hay más errores en el archivo.' : ''),
            ]);
        }

        $codes = array_values(array_unique(array_column($records, 'normalized_code')));
        $existingItemsByCode = Item::query()
            ->where('type', 'producto')
            ->whereNotNull('code')
            ->whereIn(DB::raw('LOWER(code)'), $codes)
            ->get(['id', 'code'])
            ->groupBy(static fn (Item $item): string => mb_strtolower((string) $item->code, 'UTF-8'));

        foreach ($existingItemsByCode as $code => $existingItems) {
            if ($existingItems->count() > 1) {
                $errors[] = 'El código "'.$code.'" aparece más de una vez en el inventario actual.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => implode(' ', $errors),
            ]);
        }

        [$created, $updated] = DB::transaction(function () use ($records, $existingItemsByCode): array {
            $created = 0;
            $updated = 0;

            foreach ($records as $record) {
                $attributes = [
                    'type' => 'producto',
                    'code' => $record['code'],
                    'name' => $record['name'],
                    'sale_price' => $record['sale_price'],
                    'purchase_price' => $record['purchase_price'],
                    'stock' => $record['stock'],
                    'min_stock' => $record['min_stock'],
                ];
                $existingItem = $existingItemsByCode->get($record['normalized_code'])?->first();

                if ($existingItem) {
                    $existingItem->update($attributes);
                    $updated++;

                    continue;
                }

                Item::create($attributes);
                $created++;
            }

            return [$created, $updated];
        });

        return redirect()->route('items.index')->with('success', "Importación completada: {$created} creados y {$updated} actualizados.");
    }

    /**
     * @param  iterable<array<int, null|bool|float|int|string>>  $rows
     */
    private function downloadSpreadsheet(string $filename, iterable $rows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'products-');
        if ($path === false) {
            throw new \RuntimeException('No fue posible preparar el archivo Excel.');
        }

        $writer = new Writer;

        try {
            $writer->openToFile($path);
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }

            throw $exception;
        }

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function itemAttributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sale_price' => $data['sale_price'],
            'purchase_price' => $data['type'] === 'producto' ? ($data['purchase_price'] ?? null) : null,
            'stock' => $data['type'] === 'producto' ? ($data['stock'] ?? 0) : 0,
            'min_stock' => $data['type'] === 'producto' ? ($data['min_stock'] ?? 0) : 0,
        ];
    }
}
