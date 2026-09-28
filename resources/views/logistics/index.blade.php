<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Logística</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50">
<div class="mx-auto max-w-5xl px-6 py-8">
    <a href="{{ route('dashboard') }}" class="text-sm text-teal-700">&larr; Volver al tablero</a>
    <h1 class="mt-3 text-2xl font-bold text-gray-900">Logística de rutas</h1>
    <p class="text-sm text-gray-500">Crea rutas de reparto, importa paradas por lote y planifica de la más fácil a la más difícil.</p>

    @if (session('success'))
        <div class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('logistics.store') }}" class="mt-6 grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-5">
        @csrf
        <input name="code" placeholder="Código (ej. RUTA-001)" class="rounded-md border-gray-300 sm:col-span-1" required>
        <input name="vehicle_plate" placeholder="Placa del vehículo" class="rounded-md border-gray-300 sm:col-span-1" required>
        <input name="driver_name" placeholder="Conductor" class="rounded-md border-gray-300 sm:col-span-1" required>
        <input type="date" name="route_date" class="rounded-md border-gray-300 sm:col-span-1" required>
        <button class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white sm:col-span-1">Crear ruta</button>
    </form>

    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left">Código</th>
                    <th class="p-3 text-left">Vehículo</th>
                    <th class="p-3 text-left">Conductor</th>
                    <th class="p-3 text-left">Fecha</th>
                    <th class="p-3 text-left">Estado</th>
                    <th class="p-3 text-left">Paradas</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($routes as $route)
                    <tr>
                        <td class="p-3 font-semibold">{{ $route->code }}</td>
                        <td class="p-3">{{ $route->vehicle_plate }}</td>
                        <td class="p-3">{{ $route->driver_name }}</td>
                        <td class="p-3">{{ $route->route_date->format('d/m/Y') }}</td>
                        <td class="p-3">{{ \App\Models\DeliveryRoute::STATUSES[$route->status] ?? $route->status }}</td>
                        <td class="p-3">{{ $route->stops_count }}</td>
                        <td class="p-3 text-right"><a href="{{ route('logistics.show', $route) }}" class="text-teal-700 font-semibold">Ver ruta →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-gray-400">Todavía no hay rutas creadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $routes->links() }}
</div>
</body>
</html>
