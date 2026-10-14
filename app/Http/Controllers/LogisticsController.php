<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Services\MapboxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
}
