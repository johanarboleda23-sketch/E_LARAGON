<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Tesorería & Logística</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Arqueo de Caja y Liquidación de Rutas</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-5xl space-y-6">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
            @endif

            <!-- Selector de tipo de caja -->
            <div class="flex gap-2">
                @foreach($fundTypes as $key =>$label)
                    <a href="{{ route('cash-register.index', ['fund_type' => $key]) }}" class="rounded-lg px-4 py-2 text-sm font-bold {{ $fundType ===$key ? 'bg-[#227c70] text-white' : 'border border-[#d7dfd8] bg-white text-[#227c70]' }}">{{ $label }}</a>
                @endforeach
            </div>

            <!-- MÓDULO DE LIQUIDACIÓN DE RUTAS DE LOGÍSTICA -->
            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#e6ede8] pb-4">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">🚚 Liquidación de Entregas / Rutas</h3>
                        <p class="text-xs text-[#71807a]">Selecciona un lote o ruta de entrega para liquidar los recaudos o registrar novedades por pedido.</p>
                    </div>

                    <!-- Selección de Lote / Ruta -->
                    <form method="GET" action="{{ route('cash-register.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="fund_type" value="{{ $fundType }}">
                        <select name="route_id" onchange="this.form.submit()" class="rounded-lg border-[#cbd6cf] text-xs font-bold text-[#192522] focus:ring-[#227c70]">
                            <option value="">-- Todas las rutas de hoy --</option>
                            @foreach($routes as$route)
                                <option value="{{ $route->id }}" {{ (string)$selectedRouteId === (string)$route->id ? 'selected' : '' }}>
                                    Ruta #{{ $route->id }} - {{$route->driver_name ?? 'Sin Conductor' }} ({{ $route->route_date }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <!-- Tabla de Paradas y Recaudos -->
                <div class="overflow-x-auto rounded-xl border border-[#e6ede8]">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#192522] uppercase text-white">
                            <tr>
                                <th class="p-3">Factura / Cliente</th>
                                <th class="p-3">Total Facturado</th>
                                <th class="p-3">Forma de Pago / Novedad</th>
                                <th class="p-3">Monto Recaudado</th>
                                <th class="p-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e6ede8] bg-white">
                            @forelse($deliveryStops as$stop)
                                <tr class="hover:bg-[#f8faf8]">
                                    <td class="p-3">
                                        <p class="font-bold text-[#192522]">{{ $stop->sale?->invoice_number ?? 'Parada #'.$stop->id }}</p>
                                        <p class="text-[11px] text-[#71807a]">{{ $stop->sale?->customer?->name ?? 'Cliente final' }}</p>
                                    </td>
                                    <td class="p-3 font-bold text-[#192522]">
                                        ${{ number_format($stop->sale?->total ?? 0, 2) }}
                                    </td>
                                    
                                    <form action="{{ route('cash-register.settle-stop', $stop->id) }}" method="POST">
                                        @csrf
                                        <td class="p-3">
                                            <select name="payment_action" class="w-full rounded-lg border-[#cbd6cf] text-xs font-semibold focus:ring-[#227c70]">
                                                @foreach($settlementActions as $key =>$label)
                                                    <option value="{{ $key }}" {{ $stop->payment_action ===$key ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="p-3">
                                            <input type="number" step="0.01" min="0" name="amount_paid" 
                                                   value="{{ $stop->collected_amount ?? $stop->sale?->total ?? 0 }}" 
                                                   class="w-28 rounded-lg border-[#cbd6cf] text-xs font-bold focus:ring-[#227c70]">
                                        </td>
                                        <td class="p-3 text-center">
                                            <button type="submit" class="rounded-lg bg-[#227c70] px-3 py-1.5 font-bold text-white hover:bg-[#1a5f55]">
                                                Liquidar
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-5 text-center text-xs text-[#71807a]">
                                        No hay pedidos asignados a esta ruta o fecha seleccionada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if($openSession)
                <!-- Sesión abierta: resumen en vivo + formulario de cierre -->
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">Caja abierta desde {{ $openSession->opened_at->format('d/m/Y H:i') }}</h3>
                        <span class="rounded-full bg-[#edf7f4] px-3 py-1 text-xs font-bold text-[#227c70]">Abierta</span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-[#f8faf8] p-3"><p class="text-[10px] font-bold uppercase text-[#71807a]">Base inicial</p><p class="font-bold text-[#192522]">${{ number_format($openSession->opening_balance, 2) }}</p></div>
                        <div class="rounded-lg bg-[#f8faf8] p-3"><p class="text-[10px] font-bold uppercase text-[#71807a]">Entradas (recibos de caja)</p><p class="font-bold text-[#227c70]">+${{ number_format($openSession->cash_in, 2) }}</p></div>
                        <div class="rounded-lg bg-[#f8faf8] p-3"><p class="text-[10px] font-bold uppercase text-[#71807a]">Salidas (egresos)</p><p class="font-bold text-[#b65338]">-${{ number_format($openSession->cash_out, 2) }}</p></div>
                        <div class="rounded-lg bg-[#fff8df] p-3"><p class="text-[10px] font-