<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Inventario / Finanzas</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Costos y márgenes</h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('items.index') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">📦 Inventario</a>
                <a href="{{ route('period-closing.index') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">🔒 Cierres</a>
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
            @endif

            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                <p class="text-sm text-[#71807a]">
                    El <strong>costo promedio</strong> se recalcula automáticamente con cada compra (promedio ponderado por cantidad). Define el <strong>margen</strong> que quieres ganar sobre ese costo y el sistema te sugiere el precio de venta: <code>precio = costo × (1 + margen%)</code>.
                </p>
            </section>

            <section class="flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                <div>
                    <h3 class="font-black text-[#192522]">Costos de compras del mes</h3>
                    <p class="text-xs text-[#71807a]">El informe usa las cantidades y costos registrados en las facturas de compra del periodo.</p>
                </div>
                <form method="GET" action="{{ route('costs.index') }}" class="flex items-end gap-2">
                    <label class="grid gap-1 text-xs font-bold text-[#71807a]">Mes
                        <select name="month" class="rounded-lg border-[#cbd6cf] text-sm">
                            @foreach (range(1, 12) as $monthNumber)
                                <option value="{{ $monthNumber }}" @selected($month === $monthNumber)>{{ \Illuminate\Support\Carbon::create($year, $monthNumber, 1)->locale('es')->monthName }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-bold text-[#71807a]">Año
                        <input type="number" name="year" min="2000" max="2200" value="{{ $year }}" class="w-24 rounded-lg border-[#cbd6cf] text-sm">
                    </label>
                    <button class="rounded-lg bg-[#227c70] px-4 py-2 text-sm font-bold text-white">Consultar</button>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#192522] text-xs uppercase text-white">
                        <tr>
                            <th class="p-3">Producto</th>
                            <th class="p-3 text-right">Cantidad comprada (mes)</th>
                            <th class="p-3 text-right">Costo comprado (mes)</th>
                            <th class="p-3 text-right">Costo promedio compra (mes)</th>
                            <th class="p-3 text-right">Costo promedio</th>
                            <th class="p-3 text-right">Precio de venta actual</th>
                            <th class="p-3 text-right">Margen actual</th>
                            <th class="p-3">Margen deseado (%)</th>
                            <th class="p-3 text-right">Precio sugerido</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e6ede8]">
                        @forelse($items as $item)
                            @php
                                $currentMargin = $item->currentMarginPercentage();
                                $suggested = $item->suggestedSalePrice();
                                $monthlyCost = $monthCosts->get($item->id);
                            @endphp
                            <tr>
                                <td class="p-3">
                                    <p class="font-bold text-[#192522]">{{ $item->name }}</p>
                                    @if($item->code)<p class="text-xs text-[#71807a]">{{ $item->code }}</p>@endif
                                </td>
                                <td class="p-3 text-right">{{ $monthlyCost ? number_format($monthlyCost->purchased_quantity, 4) : '—' }}</td>
                                <td class="p-3 text-right">{{ $monthlyCost ? '$'.number_format($monthlyCost->purchase_cost, 2) : '—' }}</td>
                                <td class="p-3 text-right">{{ $monthlyCost && $monthlyCost->purchased_quantity > 0 ? '$'.number_format($monthlyCost->purchase_cost / $monthlyCost->purchased_quantity, 2) : '—' }}</td>
                                <td class="p-3 text-right font-semibold {{ $item->average_cost ? 'text-[#192522]' : 'text-[#c9d2cd]' }}">
                                    {{ $item->average_cost ? '$'.number_format($item->average_cost, 2) : 'Sin compras aún' }}
                                </td>
                                <td class="p-3 text-right">${{ number_format($item->sale_price, 2) }}</td>
                                <td class="p-3 text-right font-semibold {{ $currentMargin === null ? 'text-[#c9d2cd]' : ($currentMargin >= 0 ? 'text-[#227c70]' : 'text-red-600') }}">
                                    {{ $currentMargin !== null ? $currentMargin.'%' : '—' }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ $suggested !== null ? '$'.number_format($suggested, 2) : '—' }}
                                </td>
                                <td class="p-3">
                                    <form method="POST" action="{{ route('costs.update', $item) }}" class="flex flex-col gap-1">
                                        @csrf
                                        @method('PUT')
                                        <div class="flex items-center gap-2">
                                            <input type="number" step="0.01" min="0" name="desired_margin_percentage" value="{{ $item->desired_margin_percentage }}" placeholder="Ej. 30" class="w-20 rounded-lg border-[#cbd6cf] text-sm">
                                            <span class="text-xs text-[#71807a]">%</span>
                                        </div>
                                        <label class="flex items-center gap-1 text-[10px] font-bold text-[#71807a]">
                                            <input type="checkbox" name="apply_to_sale_price" value="1"> Aplicar al precio de venta
                                        </label>
                                        <button type="submit" class="rounded-lg bg-[#227c70] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#1a5f55]">Guardar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="p-5 text-center text-sm text-[#71807a]">No hay productos registrados en el inventario.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
