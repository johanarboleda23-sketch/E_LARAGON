<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Finanzas</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Indicadores financieros</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <!-- Filtro de fechas -->
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-[#d7dfd8] bg-white p-4 shadow-sm">
                <div>
                    <label class="mb-1 block text-xs font-bold text-[#71807a]">Desde</label>
                    <input type="date" name="from" value="{{ $from }}" class="rounded-lg border-[#cbd6cf] text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-[#71807a]">Hasta</label>
                    <input type="date" name="to" value="{{ $to }}" class="rounded-lg border-[#cbd6cf] text-sm">
                </div>
                <button class="rounded-lg bg-[#227c70] px-4 py-2 text-sm font-bold text-white hover:bg-[#1a5f55]">Filtrar</button>
            </form>

            <!-- Tarjetas principales -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Ventas totales</p>
                    <p class="mt-1 text-2xl font-black text-[#192522]">${{ number_format($totalSales, 0) }}</p>
                </div>
                <div class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Compras totales</p>
                    <p class="mt-1 text-2xl font-black text-[#192522]">${{ number_format($totalPurchases, 0) }}</p>
                </div>
                <div class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Utilidad bruta</p>
                    <p class="mt-1 text-2xl font-black {{ $grossProfit >= 0 ? 'text-[#227c70]' : 'text-red-600' }}">${{ number_format($grossProfit, 0) }}</p>
                    <p class="text-xs text-[#71807a]">Margen: {{ $grossMargin !== null ? $grossMargin.'%' : '—' }}</p>
                </div>
                <div class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Utilidad neta (aprox.)</p>
                    <p class="mt-1 text-2xl font-black {{ $netProfit >= 0 ? 'text-[#227c70]' : 'text-red-600' }}">${{ number_format($netProfit, 0) }}</p>
                    <p class="text-xs text-[#71807a]">Margen: {{ $netMargin !== null ? $netMargin.'%' : '—' }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Gastos operativos</p>
                    <p class="mt-1 text-xl font-black text-[#b65338]">${{ number_format($operatingExpenses, 0) }}</p>
                </div>
                <div class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Cartera clientes (saldo pendiente)</p>
                    <p class="mt-1 text-xl font-black text-[#192522]">${{ number_format($receivables, 0) }}</p>
                </div>
                <div class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Cartera proveedores (saldo pendiente)</p>
                    <p class="mt-1 text-xl font-black text-[#192522]">${{ number_format($payables, 0) }}</p>
                </div>
                <div class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Inventario valorizado</p>
                    <p class="mt-1 text-xl font-black text-[#192522]">${{ number_format($inventoryValue, 0) }}</p>
                    <p class="text-xs text-[#71807a]">Rotación: {{ $inventoryTurnover ?? '—' }}</p>
                </div>
            </div>

            <!-- Tendencia mensual -->
            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-black uppercase tracking-wider text-[#227c70]">Tendencia: ventas vs. compras (últimos 6 meses)</h3>
                <div class="flex items-end justify-between gap-4" style="height:180px;">
                    @foreach($monthlyTrend as $row)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <div class="flex h-full w-full items-end justify-center gap-1">
                                <div class="w-1/2 rounded-t bg-[#227c70]" style="height:{{ $maxTrendValue > 0 ? max(2, round($row['sales'] / $maxTrendValue * 140)) : 2 }}px;" title="Ventas: ${{ number_format($row['sales'], 0) }}"></div>
                                <div class="w-1/2 rounded-t bg-[#d96b4c]" style="height:{{ $maxTrendValue > 0 ? max(2, round($row['purchases'] / $maxTrendValue * 140)) : 2 }}px;" title="Compras: ${{ number_format($row['purchases'], 0) }}"></div>
                            </div>
                            <span class="text-[10px] font-bold text-[#71807a]">{{ $row['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex gap-4 text-xs font-bold text-[#71807a]">
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-[#227c70]"></span> Ventas</span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-[#d96b4c]"></span> Compras</span>
                </div>
            </section>

            <p class="text-xs text-[#71807a]">
                * La utilidad bruta usa el costo promedio <strong>actual</strong> de cada producto vendido (no se guarda el costo histórico por venta), por lo que es una aproximación útil para ver la tendencia. Las carteras de clientes/proveedores muestran el saldo pendiente real a hoy (total facturado menos abonos registrados).
            </p>
        </div>
    </div>
</x-app-layout>
