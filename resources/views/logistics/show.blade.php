<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ruta {{ $route->code }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50">
<div class="mx-auto max-w-5xl px-6 py-8">
    <a href="{{ route('logistics.index') }}" class="text-sm text-teal-700">&larr; Volver a logística</a>
    <div class="mt-3 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ruta {{ $route->code }}</h1>
            <p class="text-sm text-gray-500">{{ $route->vehicle_plate }} — {{ $route->driver_name }} — {{ $route->route_date->format('d/m/Y') }} — {{ \App\Models\DeliveryRoute::STATUSES[$route->status] ?? $route->status }}</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('logistics.plan', $route) }}">
                @csrf
                <button class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Planificar (fácil → difícil)</button>
            </form>
            <a href="{{ route('logistics.manifest', $route) }}" target="_blank" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Imprimir manifiesto</a>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <form method="POST" action="{{ route('logistics.stops.import', $route) }}" enctype="multipart/form-data" class="rounded-lg border border-gray-200 bg-white p-4">
            @csrf
            <p class="text-sm font-semibold text-gray-700">Importar lote de pedidos (CSV)</p>
            <p class="mt-1 text-xs text-gray-500">Columnas: cliente, dirección, ciudad, dificultad (0-100).</p>
            <input type="file" name="file" accept=".csv,.txt" class="mt-3 w-full text-sm" required>
            <button class="mt-3 rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Importar</button>
        </form>

        <form method="POST" action="{{ route('logistics.stops.store', $route) }}" class="rounded-lg border border-gray-200 bg-white p-4">
            @csrf
            <p class="text-sm font-semibold text-gray-700">Agregar parada manual</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <input name="customer_name" placeholder="Cliente" class="rounded-md border-gray-300" required>
                <input name="address" placeholder="Dirección" class="rounded-md border-gray-300" required>
                <input name="city" placeholder="Ciudad" class="rounded-md border-gray-300">
                <input type="number" name="difficulty_score" min="0" max="100" value="50" placeholder="Dificultad" class="rounded-md border-gray-300" required>
            </div>
            <button class="mt-3 rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Agregar</button>
        </form>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left">#</th>
                    <th class="p-3 text-left">Cliente</th>
                    <th class="p-3 text-left">Dirección</th>
                    <th class="p-3 text-left">Dificultad</th>
                    <th class="p-3 text-left">Tramo</th>
                    <th class="p-3 text-left">Estado</th>
                    <th class="p-3 text-left">Registrar entrega</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($route->stops as $stop)
                    <tr>
                        <td class="p-3">{{ $stop->sequence }}</td>
                        <td class="p-3 font-semibold">{{ $stop->customer_name }}</td>
                        <td class="p-3">{{ $stop->address }}{{ $stop->city ? ', '.$stop->city : '' }}</td>
                        <td class="p-3">{{ $stop->difficulty_score }}</td>
                        <td class="p-3 text-xs text-gray-500">
                            @if ($stop->distance_meters)
                                {{ number_format($stop->distance_meters / 1000, 1) }} km — {{ gmdate('H:i', $stop->duration_seconds) }} h
                            @else
                                —
                            @endif
                        </td>
                        <td class="p-3">{{ \App\Models\DeliveryStop::STATUSES[$stop->status] ?? $stop->status }}</td>
                        <td class="p-3">
                            @if ($stop->status === 'pending')
                                <form method="POST" action="{{ route('logistics.stops.deliver', $stop) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="file" name="evidence" accept="image/*" class="text-xs">
                                    <button name="status" value="delivered" class="rounded-md bg-emerald-600 px-3 py-1 text-xs font-semibold text-white">Entregada</button>
                                    <button name="status" value="failed" class="rounded-md bg-red-600 px-3 py-1 text-xs font-semibold text-white">Fallida</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-500">{{ $stop->delivered_at?->format('d/m/Y H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-gray-400">Sin paradas todavía.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
