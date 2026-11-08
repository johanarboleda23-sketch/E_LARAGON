<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Services\InventoryUnitService;
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

    private const UNITS = [
        'unidad', 'pieza', 'par', 'docena', 'media docena', 'centena', 'millar', 'paquete', 'caja', 'bulto', 'saco',
        'set', 'kit', 'rollo', 'resma', 'botella', 'lata', 'bolsa', 'frasco', 'blíster',
        'kilogramo', 'gramo', 'miligramo', 'tonelada métrica', 'libra', 'onza',
        'litro', 'mililitro', 'centilitro', 'galón estadounidense', 'galón imperial',
        'cuarto estadounidense', 'cuarto imperial', 'pinta estadounidense', 'pinta imperial',
        'taza', 'onza líquida', 'cucharada', 'cucharadita',
        'milímetro', 'centímetro', 'metro', 'kilómetro', 'pulgada', 'pie', 'yarda', 'milla',
        'centímetro cuadrado', 'metro cuadrado', 'hectárea', 'acre',
        'hora', 'minuto', 'día',
    ];

    private const UNIT_FACTOR_OPTIONS = [
        '1', '1/2', '1/3', '1/4', '1/5', '1/6', '1/8', '1/10', '1/12', '1/16',
        '1 1/2', '1 1/3', '1 1/4', '1 1/8', '2', '3', '4', '6', '12', '24', '100', '1000',
    ];

    // Muestra la lista de productos/servicios y la opción Modal
    public function index()
    {
        $items = Item::query()
            ->with(['inventoryAccount', 'incomeAccount', 'unitConversions'])
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

        return view('items.index', [
            'items' => $items,
            'postingAccounts' => $postingAccounts,
            'unitOptions' => self::UNITS,
            'unitFactorOptions' => self::UNIT_FACTOR_OPTIONS,
        ]);
    }

    // Muestra el formulario en una página completa
    public function create()
    {
        return view('items.create', [
            'unitOptions' => self::UNITS,
            'unitFactorOptions' => self::UNIT_FACTOR_OPTIONS,
        ]);
    }

    // Guarda el producto o servicio en Laragon
    public function store(Request $request)
    {
        $this->normalizeUnitConversionFactors($request);
        $data = $request->validate([
            'type' => 'required|in:producto,servicio',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'base_unit' => ['required_if:type,producto', 'nullable', 'string', 'max:40'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'unit_conversions' => ['nullable', 'array'],
            'unit_conversions.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'unit_conversions.*.factor' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'unit_conversions.*.sale_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->validateUnitNames($data);

        DB::transaction(function () use ($data): void {
            $item = Item::create($this->itemAttributes($data));
            $this->syncUnitConversions($item, $data['unit_conversions'] ?? []);
        });

        return redirect()->route('items.index')->with('success', 'Registro creado con éxito.');
    }

    public function edit(Item $item)
    {
        $item->load('unitConversions');

        return view('items.edit', [
            'item' => $item,
            'unitOptions' => self::UNITS,
            'unitFactorOptions' => self::UNIT_FACTOR_OPTIONS,
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $this->normalizeUnitConversionFactors($request);
        $data = $request->validate([
            'type' => 'required|in:producto,servicio',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'base_unit' => ['required_if:type,producto', 'nullable', 'string', 'max:40'],
            'stock' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'unit_conversions' => ['nullable', 'array'],
            'unit_conversions.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'unit_conversions.*.factor' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'unit_conversions.*.sale_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->validateUnitNames($data);

        DB::transaction(function () use ($data, $item): void {
            $item->update($this->itemAttributes($data));
            $this->syncUnitConversions($item, $data['unit_conversions'] ?? []);
        });

        return redirect()->route('items.index')->with('success', 'Registro actualizado con éxito.');
    }

    public function destroy(Item $item)
    {
        $deleted = DB::transaction(function () use ($item): bool {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);

            if ($this->hasHistory($item)) {
                return false;
            }

            $item->delete();

            return true;
        });

        if (! $deleted) {
            return back()->withErrors([
                'item' => 'No se puede eliminar este registro porque ya tiene movimientos o documentos asociados. Conserva el registro para mantener el historial.',
            ]);
        }

        return redirect()->route('items.index')->with('success', 'Registro eliminado con éxito.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*' => [
                'required',
                'integer',
                'distinct',
            ],
        ], [
            'items.required' => 'Selecciona al menos un registro para eliminar.',
            'items.min' => 'Selecciona al menos un registro para eliminar.',
            'items.max' => 'Puedes eliminar hasta 1.000 registros a la vez.',
        ]);

        [$deletedCount, $protectedCount] = DB::transaction(function () use ($data): array {
            $items = Item::query()
                ->whereKey($data['items'])
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o más registros no pertenecen a la empresa activa.',
                ]);
            }

            $itemIds = $items->modelKeys();
            $itemIdsWithHistory = collect([
                DB::table('purchase_details')->whereIn('item_id', $itemIds)->distinct()->pluck('item_id'),
                DB::table('sale_details')->whereIn('item_id', $itemIds)->distinct()->pluck('item_id'),
                DB::table('commercial_document_lines')->whereIn('item_id', $itemIds)->distinct()->pluck('item_id'),
                DB::table('inventory_movements')->whereIn('item_id', $itemIds)->distinct()->pluck('item_id'),
            ])->flatten()->unique()->flip();
            $deletedCount = 0;
            $protectedCount = 0;

            foreach ($items as $item) {
                if ($itemIdsWithHistory->has($item->id)) {
                    $protectedCount++;

                    continue;
                }

                $item->delete();
                $deletedCount++;
            }

            return [$deletedCount, $protectedCount];
        });

        if ($protectedCount > 0) {
            return back()->with('warning', "Se eliminaron {$deletedCount} registros. Se conservaron {$protectedCount} porque tienen movimientos o documentos asociados.");
        }

        return back()->with('success', "Se eliminaron {$deletedCount} registros.");
    }

    private function hasHistory(Item $item): bool
    {
        return $item->purchaseDetails()->withoutGlobalScopes()->exists()
            || $item->saleDetails()->withoutGlobalScopes()->exists()
            || $item->commercialDocumentLines()->withoutGlobalScopes()->exists()
            || $item->inventoryMovements()->withoutGlobalScopes()->exists();
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

    public function adjustStock(Request $request, Item $item, InventoryUnitService $inventoryUnitService)
    {
        abort_if($item->type !== 'producto', 422, 'Los servicios no manejan existencias.');

        $data = $request->validate([
            'movement_type' => 'required|in:entrada,salida',
            'unit_conversion_id' => ['nullable', 'integer', 'exists:item_unit_conversions,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => 'nullable|string|max:255',
        ]);

        $unit = $inventoryUnitService->snapshot($item, isset($data['unit_conversion_id']) ? (int) $data['unit_conversion_id'] : null, 'unit_conversion_id');
        $baseQuantity = round((float) $data['quantity'] * $unit['unit_factor'], 4);

        DB::transaction(function () use ($data, $item, $baseQuantity): void {
            $lockedItem = Item::query()->lockForUpdate()->findOrFail($item->id);
            $stockBefore = (float) $lockedItem->stock;
            $stockAfter = $data['movement_type'] === 'entrada'
                ? $stockBefore + $baseQuantity
                : $stockBefore - $baseQuantity;

            abort_if($stockAfter < 0, 422, 'La salida supera el stock disponible.');

            $lockedItem->update(['stock' => $stockAfter]);
            InventoryMovement::create([
                'item_id' => $lockedItem->id,
                'type' => $data['movement_type'],
                'quantity' => $baseQuantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $data['reason'] ?? 'Movimiento manual',
            ]);
        });

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
                'stock' => ['nullable', 'numeric', 'min:0'],
                'min_stock' => ['nullable', 'numeric', 'min:0'],
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

                Item::create([...$attributes, 'base_unit' => 'unidad']);
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
            'base_unit' => $data['type'] === 'producto' ? ($data['base_unit'] ?? 'unidad') : null,
            'stock' => $data['type'] === 'producto' ? ($data['stock'] ?? 0) : 0,
            'min_stock' => $data['type'] === 'producto' ? ($data['min_stock'] ?? 0) : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateUnitNames(array $data): void
    {
        $baseUnit = mb_strtolower(trim((string) ($data['base_unit'] ?? '')), 'UTF-8');

        foreach ($data['unit_conversions'] ?? [] as $index => $conversion) {
            if (mb_strtolower(trim($conversion['name']), 'UTF-8') === $baseUnit) {
                throw ValidationException::withMessages([
                    "unit_conversions.{$index}.name" => 'La presentación debe ser distinta de la unidad base.',
                ]);
            }
        }

    }

    private function normalizeUnitConversionFactors(Request $request): void
    {
        $conversions = $request->input('unit_conversions');
        if (! is_array($conversions)) {
            return;
        }

        foreach ($conversions as $index => $conversion) {
            if (! is_array($conversion) || ! isset($conversion['factor']) || ! is_string($conversion['factor'])) {
                continue;
            }

            $factor = trim($conversion['factor']);
            if ($factor === '') {
                continue;
            }

            $factor = str_replace(',', '.', $factor);
            if (preg_match('/^\d+(?:\.\d+)?$/', $factor) === 1) {
                $conversions[$index]['factor'] = (float) $factor;

                continue;
            }

            if (preg_match('/^(?:(\d+)\s+)?(\d+)\s*\/\s*(\d+)$/', $factor, $matches) === 1) {
                $whole = (int) ($matches[1] ?? 0);
                $numerator = (int) $matches[2];
                $denominator = (int) $matches[3];
                if ($denominator > 0 && $numerator > 0 && $numerator < $denominator) {
                    $conversions[$index]['factor'] = $whole + ($numerator / $denominator);

                    continue;
                }
            }

            throw ValidationException::withMessages([
                "unit_conversions.{$index}.factor" => 'Escribe una conversión decimal o fraccionaria, por ejemplo 0.5, 1/2 o 1 1/2.',
            ]);
        }

        $request->merge(['unit_conversions' => $conversions]);
    }

    /**
     * @param  array<int, array{name: string, factor: int|float|string, sale_price?: int|float|string|null}>  $conversions
     */
    private function syncUnitConversions(Item $item, array $conversions): void
    {
        $item->unitConversions()->delete();

        if ($item->type !== 'producto') {
            return;
        }

        foreach ($conversions as $conversion) {
            $item->unitConversions()->create([
                'name' => trim($conversion['name']),
                'factor' => $conversion['factor'],
                'sale_price' => $conversion['sale_price'] ?? null,
            ]);
        }
    }
}
