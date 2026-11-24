<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase text-[#d96b4c]">Operaciones</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Logística de rutas</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-sm font-bold text-[#227c70]">&larr; Tablero</a>
        </div>
    </x-slot>
<div class="min-h-screen bg-[#f3f5f1] px-4 py-6 sm:px-8">
<div class="mx-auto max-w-7xl">

    @if (session('success'))
        <div role="status" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <h1 class="mb-4 text-lg font-bold text-[#192522]">Nueva ruta</h1>
    <form method="POST" action="{{ route('logistics.store') }}" class="grid grid-cols-1 items-end gap-3 border-b border-[#d7dfd8] pb-6 sm:grid-cols-2 lg:grid-cols-5">
        @csrf
        <label class="grid gap-1 text-xs font-bold text-[#52635b]">Código
            <input name="code" value="{{ old('code') }}" placeholder="RUTA-001" class="w-full rounded-md border-[#cbd6cf] text-sm" required>
        </label>
        <label class="grid gap-1 text-xs font-bold text-[#52635b]">Placa del vehículo
            <input name="vehicle_plate" value="{{ old('vehicle_plate') }}" class="w-full rounded-md border-[#cbd6cf] text-sm" required>
        </label>
        <label class="grid gap-1 text-xs font-bold text-[#52635b]">Conductor
            <input name="driver_name" value="{{ old('driver_name') }}" class="w-full rounded-md border-[#cbd6cf] text-sm" required>
        </label>
        <label class="grid gap-1 text-xs font-bold text-[#52635b]">Fecha
            <input type="date" name="route_date" value="{{ old('route_date') }}" class="w-full rounded-md border-[#cbd6cf] text-sm" required>
        </label>
        <button class="rounded-md bg-[#227c70] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1b665c]">Crear ruta</button>
    </form>

    <div class="mt-6 overflow-x-auto border border-[#d7dfd8] bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-[#192522] text-white">
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
</div>
</x-app-layout>
