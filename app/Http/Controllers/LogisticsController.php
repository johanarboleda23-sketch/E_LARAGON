<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterMovement;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\Sale;
use App\Services\MapboxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LogisticsController extends Controller
{
    public function __construct(private readonly MapboxService $mapbox) {}

    public function index(): View
    {
        $routes = DeliveryRoute::query()
            ->withCount('stops')
            ->latest('route_date')
            ->latest('id')
            ->paginate(15);

        return view('logistics.index', compact('routes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'vehicle_plate' => ['required', 'string', 'max:20'],
            'driver_name' => ['required', 'string', 'max:255'],
            'route_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $route = DeliveryRoute::create([
            ...$data,
            'company_id' => (int) session('company_id'),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('logistics.show', $route)->with('success', 'Ruta creada. Ahora importa o agrega las paradas.');
    }

    public function show(DeliveryRoute $route): View
    {
        $route->load('stops');

        return view('logistics.show', compact('route'));
    }

    public function importStops(Request $request, DeliveryRoute $route): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($data['file']->getRealPath(), 'r');
        $header = fgetcsv($handle, escape: '\\');
        $created = 0;
        $nextSequence = (int) $route->stops()->max('sequence') + 1;

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (count(array_filter($row)) === 0) {
                continue;
            }

            $customerName = trim($row[0] ?? '');
            $address = trim($row[1] ?? '');

            if ($customerName === '' || $address === '') {
                continue;
            }

            DeliveryStop::create([
                'delivery_route_id' => $route->id,
                'sequence' => $nextSequence++,
                'customer_name' => $customerName,
                'address' => $address,
                'city' => trim($row[2] ?? '') ?: null,
                'difficulty_score' => (int) ($row[3] ?? 50),
                'status' => 'pending',
            ]);
            $created++;
        }

        fclose($handle);

        return back()->with('success', "Se importaron {$created} paradas desde el archivo.");
    }

    public function storeStop(Request $request, DeliveryRoute $route): RedirectResponse
    {
        $data = $request->validate([
            'third_party_id' => ['nullable', 'integer', 'exists:third_parties,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'difficulty_score' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        DeliveryStop::create([
            ...$data,
            'delivery_route_id' => $route->id,
            'sequence' => (int) $route->stops()->max('sequence') + 1,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Parada agregada.');
    }

    public function plan(DeliveryRoute $route): RedirectResponse
    {
        if (! $this->mapbox->isConfigured()) {
            return $this->planByDifficulty($route)
                ? back()->with('success', 'Ruta planificada de la parada más fácil a la más difícil (sin mapa: configura MAPBOX_TOKEN para usar distancia y tráfico reales).')
                : back()->with('error', 'No hay paradas para planificar.');
        }

        $stops = $route->stops()->orderBy('sequence')->orderBy('id')->get();

        if ($stops->isEmpty()) {
            return back()->with('error', 'No hay paradas para planificar.');
        }

        foreach ($stops as $stop) {
            if ($stop->latitude && $stop->longitude) {
                continue;
            }

            $address = trim($stop->address.', '.$stop->city);
            $coordinates = $this->mapbox->geocode($address);

            if (! $coordinates) {
                continue;
            }

            $stop->update(['latitude' => $coordinates[0], 'longitude' => $coordinates[1]]);
        }

        $geocodedStops = $stops->filter(fn (DeliveryStop $stop) => $stop->latitude && $stop->longitude)->values();

        if ($geocodedStops->count() < 2) {
            $this->planByDifficulty($route);

            return back()->with('error', 'No se pudieron ubicar suficientes direcciones en el mapa. Se planificó por dificultad manual como respaldo.');
        }

        $coordinates = $geocodedStops->map(fn (DeliveryStop $stop): array => [(float) $stop->latitude, (float) $stop->longitude])->all();
        $optimized = $this->mapbox->optimizeOrder($coordinates);

        if (! $optimized) {
            $this->planByDifficulty($route);

            return back()->with('error', 'Mapbox no pudo optimizar la ruta en este momento. Se planificó por dificultad manual como respaldo.');
        }

        DB::transaction(function () use ($optimized, $geocodedStops): void {
            foreach ($optimized as $position => $entry) {
                $stop = $geocodedStops->get($entry['index']);
                $stop->update([
                    'sequence' => $position + 1,
                    'duration_seconds' => $entry['duration'],
                    'distance_meters' => $entry['distance'],
                ]);
            }
        });

        $route->update(['status' => 'planned']);

        return back()->with('success', 'Ruta planificada con Mapbox: orden optimizado por distancia y tiempo real de viaje.');
    }

    private function planByDifficulty(DeliveryRoute $route): bool
    {
        $stops = $route->stops()->orderBy('difficulty_score')->orderBy('id')->get();

        if ($stops->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($route, $stops): void {
            foreach ($stops as $index => $stop) {
                $stop->update(['sequence' => $index + 1]);
            }

            $route->update(['status' => 'planned']);
        });

        return true;
    }

    public function deliverStop(Request $request, DeliveryStop $stop): RedirectResponse
    {
        abort_if($stop->status === 'delivered', 422, 'Esta entrega ya fue confirmada por el conductor y no se puede modificar.');

        $data = $request->validate([
            'status' => ['required', 'string', 'in:delivered,failed'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'image', 'max:5120'],
        ]);

        $evidencePath = $data['evidence'] ?? null
            ? $request->file('evidence')->store('delivery-evidence')
            : $stop->evidence_path;

        $stop->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $stop->notes,
            'evidence_path' => $evidencePath,
            'delivered_at' => now(),
        ]);

        $route = $stop->route;
        if ($route->stops()->where('status', 'pending')->doesntExist()) {
            $route->update(['status' => 'completed']);
        } elseif ($route->status === 'planned') {
            $route->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Estado de la parada actualizado.');
    }

    public function manifest(DeliveryRoute $route): View
    {
        $route->load('stops');

        return view('logistics.manifest', compact('route'));
    }

    public function evidence(DeliveryStop $stop)
    {
        abort_unless($stop->evidence_path, 404);

        return Storage::disk()->response($stop->evidence_path);
    }

    /**
     * Vincula una factura de venta existente a una parada de la ruta, para que el arqueo de caja
     * pueda capturar su forma de pago en vivo.
     */
    public function attachSale(Request $request, DeliveryStop $stop): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string'],
        ]);

        $sale = Sale::query()->where('invoice_number', $data['invoice_number'])->first();
        abort_unless($sale, 404, 'No se encontró ninguna factura con ese número.');

        $stop->update(['sale_id' => $sale->id]);

        return back()->with('success', 'Factura '.$sale->invoice_number.' vinculada a la parada.');
    }

    /**
     * Quita la factura del lote de logística (no elimina la factura en sí, solo su vínculo con
     * la ruta) y borra cualquier movimiento de caja que se hubiera generado a partir de ella.
     */
    public function detachSale(DeliveryStop $stop): RedirectResponse
    {
        CashRegisterMovement::where('delivery_stop_id', $stop->id)->delete();
        $stop->update([
            'sale_id' => null,
            'payment_action' => null,
            'payment_amount' => null,
            'payment_recorded_at' => null,
        ]);

        return back()->with('success', 'Factura retirada del lote de logística.');
    }

    /**
     * Registra la acción de pago/devolución/novedad elegida para la parada desde el desplegable:
     * 1105 Efectivo, 1120 Banco, 1305 Crédito, Devolución total/parcial, Cruce de productos,
     * Volver a programar o Cobro de cartera.
     */
    public function recordAction(Request $request, DeliveryStop $stop): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(array_keys(DeliveryStop::PAYMENT_ACTIONS))],
        ]);

        return match ($data['action']) {
            'cash', 'bank', 'credit' => $this->recordPaymentMethod($stop, $data['action']),
            'full_return' => $this->recordFullReturn($stop),
            'reschedule' => $this->recordReschedule($request, $stop),
            default => back()->withErrors(['action' => 'Esta acción requiere su propio formulario.']),
        };
    }

    private function recordPaymentMethod(DeliveryStop $stop, string $method): RedirectResponse
    {
        abort_unless($stop->sale_id, 422, 'Esta parada no tiene una factura vinculada todavía.');

        CashRegisterMovement::where('delivery_stop_id', $stop->id)->delete();

        $sale = $stop->sale;
        CashRegisterMovement::create([
            'delivery_stop_id' => $stop->id,
            'sale_id' => $sale->id,
            'movement_type' => $method,
            'amount' => $sale->total,
            'is_collection' => false,
            'description' => 'Entrega '.$sale->invoice_number,
            'created_by' => auth()->id(),
        ]);

        $stop->update([
            'payment_action' => $method,
            'payment_amount' => $sale->total,
            'payment_recorded_at' => now(),
        ]);

        return back()->with('success', 'Forma de pago registrada: '.DeliveryStop::PAYMENT_ACTIONS[$method]);
    }

    private function recordFullReturn(DeliveryStop $stop): RedirectResponse
    {
        abort_unless($stop->sale_id, 422, 'Esta parada no tiene una factura vinculada todavía.');

        DB::transaction(function () use ($stop): void {
            $sale = $stop->sale()->with('details.item')->first();
            foreach ($sale->details as $detail) {
                if (! $detail->item) {
                    continue;
                }
                $item = Item::lockForUpdate()->find($detail->item->id);
                $stockBefore = $item->stock;
                $item->increment('stock', $detail->quantity);
                $item->refresh();
                InventoryMovement::create([
                    'item_id' => $item->id,
                    'type' => 'entrada',
                    'quantity' => $detail->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $item->stock,
                    'reason' => 'Devolución total en entrega, factura '.$sale->invoice_number,
                ]);
            }

            CashRegisterMovement::where('delivery_stop_id', $stop->id)->delete();
            $stop->update([
                'payment_action' => 'full_return',
                'payment_amount' => 0,
                'payment_recorded_at' => now(),
            ]);
        });

        return back()->with('success', 'Devolución total registrada: todos los productos regresaron al inventario.');
    }

    /**
     * Devuelve al inventario solo las cantidades seleccionadas de cada producto de la factura, y
     * registra el movimiento de caja únicamente por el valor neto efectivamente cobrado.
     */
    public function recordPartialReturn(Request $request, DeliveryStop $stop): RedirectResponse
    {
        abort_unless($stop->sale_id, 422, 'Esta parada no tiene una factura vinculada todavía.');

        $data = $request->validate([
            'method' => ['required', 'string', 'in:cash,bank,credit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_detail_id' => ['required', 'integer', 'exists:sale_details,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $stop): void {
            $sale = $stop->sale()->with('details.item')->first();
            $returnedValue = 0.0;

            foreach ($data['items'] as $row) {
                $quantity = (int) $row['quantity'];
                if ($quantity <= 0) {
                    continue;
                }

                $detail = $sale->details->firstWhere('id', $row['sale_detail_id']);
                abort_unless($detail, 422, 'Un producto seleccionado no pertenece a esta factura.');
                abort_if($quantity > $detail->quantity, 422, 'No puedes devolver más unidades de las facturadas.');

                if ($detail->item) {
                    $item = Item::lockForUpdate()->find($detail->item->id);
                    $stockBefore = $item->stock;
                    $item->increment('stock', $quantity);
                    $item->refresh();
                    InventoryMovement::create([
                        'item_id' => $item->id,
                        'type' => 'entrada',
                        'quantity' => $quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $item->stock,
                        'reason' => 'Devolución parcial en entrega, factura '.$sale->invoice_number,
                    ]);
                }

                $returnedValue += $quantity * (float) $detail->unit_price;
            }

            $netAmount = max((float) $sale->total - $returnedValue, 0);

            CashRegisterMovement::where('delivery_stop_id', $stop->id)->delete();
            CashRegisterMovement::create([
                'delivery_stop_id' => $stop->id,
                'sale_id' => $sale->id,
                'movement_type' => $data['method'],
                'amount' => $netAmount,
                'is_collection' => false,
                'description' => 'Entrega con devolución parcial '.$sale->invoice_number,
                'created_by' => auth()->id(),
            ]);

            $stop->update([
                'payment_action' => 'partial_return',
                'payment_amount' => $netAmount,
                'payment_recorded_at' => now(),
            ]);
        });

        return back()->with('success', 'Devolución parcial registrada: los productos seleccionados regresaron al inventario.');
    }

    /**
     * Cruza un producto de la factura por otro (ej. el cliente pide cambiarlo en el momento de la
     * entrega): descuenta stock del nuevo producto y repone el stock del producto original.
     */
    public function recordProductExchange(Request $request, DeliveryStop $stop): RedirectResponse
    {
        abort_unless($stop->sale_id, 422, 'Esta parada no tiene una factura vinculada todavía.');

        $data = $request->validate([
            'sale_detail_id' => ['required', 'integer', 'exists:sale_details,id'],
            'new_item_id' => ['required', 'integer', 'exists:items,id'],
        ]);

        DB::transaction(function () use ($data, $stop): void {
            $sale = $stop->sale()->with('details.item')->first();
            $detail = $sale->details->firstWhere('id', $data['sale_detail_id']);
            abort_unless($detail, 422, 'El producto seleccionado no pertenece a esta factura.');

            $newItem = Item::lockForUpdate()->find($data['new_item_id']);
            abort_if($newItem->stock < $detail->quantity, 422, 'El producto de cambio no tiene stock suficiente.');

            if ($detail->item) {
                $oldItem = Item::lockForUpdate()->find($detail->item->id);
                $stockBefore = $oldItem->stock;
                $oldItem->increment('stock', $detail->quantity);
                $oldItem->refresh();
                InventoryMovement::create([
                    'item_id' => $oldItem->id,
                    'type' => 'entrada',
                    'quantity' => $detail->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $oldItem->stock,
                    'reason' => 'Cruce de productos en entrega, factura '.$sale->invoice_number,
                ]);
            }

            $stockBeforeNew = $newItem->stock;
            $newItem->decrement('stock', $detail->quantity);
            $newItem->refresh();
            InventoryMovement::create([
                'item_id' => $newItem->id,
                'type' => 'salida',
                'quantity' => $detail->quantity,
                'stock_before' => $stockBeforeNew,
                'stock_after' => $newItem->stock,
                'reason' => 'Cruce de productos en entrega, factura '.$sale->invoice_number,
            ]);

            $detail->update(['item_id' => $newItem->id]);

            $stop->update([
                'payment_action' => 'product_exchange',
                'payment_recorded_at' => now(),
            ]);
        });

        return back()->with('success', 'Cruce de productos registrado correctamente.');
    }

    private function recordReschedule(Request $request, DeliveryStop $stop): RedirectResponse
    {
        $data = $request->validate([
            'reschedule_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        CashRegisterMovement::where('delivery_stop_id', $stop->id)->delete();
        $stop->update([
            'status' => 'pending',
            'payment_action' => 'reschedule',
            'payment_amount' => null,
            'payment_recorded_at' => now(),
            'reschedule_date' => $data['reschedule_date'],
            'delivered_at' => null,
        ]);

        return back()->with('success', 'Entrega reprogramada para el '.$data['reschedule_date'].'.');
    }

    /**
     * Registra el cobro de una cartera anterior (no afecta inventario): crea el abono sobre la
     * factura con saldo pendiente y su movimiento de caja/banco correspondiente.
     */
    public function recordCollection(Request $request, DeliveryStop $stop): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'in:cash,bank'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        abort_unless($stop->sale_id, 422, 'Esta parada no tiene una factura vinculada todavía.');
        $sale = $stop->sale;
        $balance = $sale->balanceDue((float) $sale->total);
        abort_if($data['amount'] > $balance + 0.01, 422, 'El cobro no puede superar el saldo pendiente ($'.number_format($balance, 2).').');

        DB::transaction(function () use ($data, $stop, $sale): void {
            $sale->payments()->create([
                'amount' => $data['amount'],
                'payment_date' => now()->toDateString(),
                'method' => DeliveryStop::PAYMENT_ACTIONS['credit_collection'],
                'notes' => 'Cobro de cartera en ruta de logística',
                'created_by' => auth()->id(),
            ]);

            CashRegisterMovement::create([
                'delivery_stop_id' => $stop->id,
                'sale_id' => $sale->id,
                'movement_type' => $data['method'],
                'amount' => $data['amount'],
                'is_collection' => true,
                'description' => 'Cobro de cartera '.$sale->invoice_number,
                'created_by' => auth()->id(),
            ]);

            $stop->update([
                'payment_action' => 'credit_collection',
                'payment_amount' => $data['amount'],
                'payment_recorded_at' => now(),
            ]);
        });

        return back()->with('success', 'Cobro de cartera registrado correctamente.');
    }
}
