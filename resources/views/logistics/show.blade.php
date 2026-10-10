<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ruta {{ $route->code }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-[#f3f5f1]">
<div class="mx-auto max-w-6xl px-6 py-8">
    <a href="{{ route('logistics.index') }}" class="text-sm font-bold text-[#227c70]">&larr; Volver a logística</a>

    <section class="mt-3 rounded-2xl bg-[#192522] p-6 text-white shadow-lg">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-[#f5c96a]">Ruta de entrega</p>
                <h1 class="mt-1 text-2xl font-black tracking-tight">{{ $route->code }}</h1>
                <p class="mt-2 text-sm text-[#b9c9c1]">🚚 {{ $route->vehicle_plate }} &nbsp;·&nbsp; 👤 {{ $route->driver_name }} &nbsp;·&nbsp; 📅 {{ $route->route_date->format('d/m/Y') }}</p>
            </div>
            <span class="rounded-full bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-wide text-[#86c8bb]">{{ \App\Models\DeliveryRoute::STATUSES[$route->status] ?? $route->status }}</span>
        </div>
        <div class="mt-5 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('logistics.plan', $route) }}">
                @csrf
                <button class="rounded-lg bg-[#f5c96a] px-4 py-2.5 text-sm font-bold text-[#263b36]">Planificar (fácil → difícil)</button>
            </form>
            <a href="{{ route('logistics.manifest', $route) }}" target="_blank" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm font-bold text-white">🖨 Imprimir manifiesto</a>
        </div>
    </section>

    @if (session('success'))
        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <form method="POST" action="{{ route('logistics.stops.import', $route) }}" enctype="multipart/form-data" class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
            @csrf
            <p class="text-sm font-bold text-[#192522]">📥 Importar lote de pedidos (CSV)</p>
            <p class="mt-1 text-xs text-[#71807a]">Columnas: cliente, dirección, ciudad, dificultad (0-100).</p>
            <input type="file" name="file" accept=".csv,.txt" class="mt-3 w-full text-sm" required>
            <button class="mt-3 rounded-lg bg-[#227c70] px-4 py-2 text-sm font-bold text-white">Importar</button>
        </form>

        <form method="POST" action="{{ route('logistics.stops.store', $route) }}" class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
            @csrf
            <p class="text-sm font-bold text-[#192522]">➕ Agregar parada manual</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <input name="customer_name" placeholder="Cliente" class="rounded-lg border-[#cbd6cf] text-sm" required>
                <input name="address" placeholder="Dirección" class="rounded-lg border-[#cbd6cf] text-sm" required>
                <input name="city" placeholder="Ciudad" class="rounded-lg border-[#cbd6cf] text-sm">
                <input type="number" name="difficulty_score" min="0" max="100" value="50" placeholder="Dificultad" class="rounded-lg border-[#cbd6cf] text-sm" required>
            </div>
            <button class="mt-3 rounded-lg bg-[#227c70] px-4 py-2 text-sm font-bold text-white">Agregar</button>
        </form>
    </div>

    <div class="mt-6 grid gap-3">
        @forelse ($route->stops as $stop)
            @php
                $isDelivered = $stop->status === 'delivered';
                $isFailed = $stop->status === 'failed';
            @endphp
            <article class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5 {{ $isDelivered ? 'border-emerald-200' : ($isFailed ? 'border-red-200' : 'border-[#d7dfd8]') }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#192522] text-sm font-black text-white">{{ $stop->sequence }}</span>
                        <div>
                            <p class="font-black text-[#192522]">{{ $stop->customer_name }}</p>
                            <p class="text-xs text-[#71807a]">{{ $stop->address }}{{ $stop->city ? ', '.$stop->city : '' }}</p>
                            <p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-[#8b9992]">
                                Dificultad {{ $stop->difficulty_score }}
                                @if ($stop->distance_meters)
                                    &nbsp;·&nbsp; {{ number_format($stop->distance_meters / 1000, 1) }} km &nbsp;·&nbsp; {{ gmdate('H:i', $stop->duration_seconds) }} h de tramo
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="w-full sm:w-auto">
                        @if ($stop->status === 'pending')
                            <form method="POST" action="{{ route('logistics.stops.deliver', $stop) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <label class="rounded-lg border border-[#cbd6cf] px-3 py-2 text-xs font-bold text-[#52635b]">
                                    📷 Evidencia
                                    <input type="file" name="evidence" accept="image/*" class="mt-1 block text-xs">
                                </label>
                                <button name="status" value="delivered" onclick="return confirm('¿Confirmas que el pedido fue entregado al cliente? Esta acción no se podrá deshacer.')" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-emerald-700">✓ Confirmar entrega</button>
                                <button name="status" value="failed" class="rounded-lg bg-red-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-red-700">✕ No entregado</button>
                            </form>
                        @elseif ($isDelivered)
                            <div class="flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-2.5 text-right">
                                <span class="text-lg">🔒✅</span>
                                <div class="text-xs">
                                    <p class="font-black text-emerald-700">Entrega confirmada</p>
                                    <p class="text-emerald-600">{{ $stop->delivered_at?->format('d/m/Y h:i A') }}</p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2 rounded-lg bg-red-50 px-4 py-2.5 text-right">
                                <span class="text-lg">⚠</span>
                                <div class="text-xs">
                                    <p class="font-black text-red-700">No entregado</p>
                                    <p class="text-red-600">{{ $stop->delivered_at?->format('d/m/Y h:i A') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-[#d7dfd8] bg-white p-10 text-center text-sm text-[#71807a]">Sin paradas todavía.</p>
        @endforelse
    </div>
</div>
</body>
</html>
