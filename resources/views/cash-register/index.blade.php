<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Tesorería & Logística</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Panel operativo</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-6 sm:px-8 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-5">
            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-sm">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 shadow-sm">{{ $errors->first() }}</div>
            @endif

            <section class="overflow-hidden rounded-3xl bg-[#192522] px-6 py-6 text-white shadow-lg sm:px-8 sm:py-7">
                <div class="flex flex-wrap items-center justify-between gap-6">
                    <div class="max-w-2xl">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[#9bc9b4]">Control diario · Tesorería</p>
                        <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Arqueo y control de caja</h1>
                        <p class="mt-2 max-w-xl text-sm leading-relaxed text-[#c2d0c9]">Administra recaudos, gastos y cierres en un solo lugar. Selecciona un lote para registrar su liquidación.</p>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <span class="h-2.5 w-2.5 rounded-full {{ $openSession ? 'bg-emerald-400' : 'bg-[#d96b4c]' }}"></span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#b9c9c1]">Estado de caja</p>
                            <p class="mt-0.5 text-sm font-bold">{{ $openSession ? 'Sesión abierta' : 'Sin sesión abierta' }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                <div>
                    <h3 class="text-sm font-black text-[#192522]">Consulta de lote</h3>
                    <p class="mt-1 text-xs text-[#71807a]">Elige una ruta para revisar y liquidar sus facturas.</p>
                </div>
                <div class="flex flex-wrap items-end gap-4">
                    <form method="GET" action="{{ route('cash-register.index') }}" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="fund_type" value="{{ $fundType }}">
                        <label class="grid gap-1 text-xs font-bold text-[#52635b]">
                            Lote / ruta
                            <select name="route_id" class="min-w-64 rounded-lg border-[#cbd6cf] text-sm">
                                <option value="">Selecciona lote</option>
                                @php foreach ($routes as $route): @endphp
                                    <option value="{{ $route->id }}" @selected((string) $selectedRouteId === (string) $route->id)>
                                        {{ $route->code }} · {{ $route->driver_name }} · {{ $route->route_date->format('d/m/Y') }}
                                    </option>
                                @php endforeach; @endphp
                            </select>
                        </label>
                        <button class="rounded-lg bg-[#227c70] px-4 py-2.5 text-xs font-bold text-white transition hover:bg-[#1b665c]">Cargar lote</button>
                    </form>

                    @if ($openSession)
                        <div class="rounded-xl border border-[#e6ede8] bg-[#f8faf8] px-4 py-3 text-sm">
                            <span class="font-bold text-[#71807a]">Base del día</span>
                            <span class="ml-2 font-black text-[#192522]">${{ number_format($openSession->opening_balance, 2) }}</span>
                            <span class="ml-3 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Caja abierta</span>
                        </div>
                    @endif
                </div>
            </section>

            @if (! $openSession)
                <section class="rounded-2xl border border-[#cbded4] bg-gradient-to-br from-white to-[#edf5ef] p-6 shadow-sm">
                    <div>
                        <h3 class="text-sm font-black text-[#227c70]">Apertura de caja</h3>
                        <p class="mt-1 text-xs text-[#71807a]">Registra el fondo disponible para iniciar las operaciones del día.</p>
                    </div>
                    <form method="POST" action="{{ route('cash-register.open') }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="grid gap-1 text-xs font-bold text-[#52635b]">
                            Caja
                            <select name="fund_type" class="rounded-lg border-[#cbd6cf] text-sm">
                                @php foreach ($fundTypes as $key => $label): @endphp
                                    <option value="{{ $key }}" @selected($fundType === $key)>{{ $label }}</option>
                                @php endforeach; @endphp
                            </select>
                        </label>
                        <label class="grid gap-1 text-xs font-bold text-[#52635b]">
                            Base inicial en efectivo
                            <input type="number" name="opening_balance" min="0" step="0.01" value="{{ old('opening_balance', 0) }}" class="rounded-lg border-[#cbd6cf] text-sm" required>
                        </label>
                        <button class="rounded-lg bg-[#227c70] px-5 py-2.5 text-xs font-bold text-white transition hover:bg-[#1b665c]">Abrir caja</button>
                    </form>
                </section>
            @endif

            @if ($selectedRoute)
                <section class="flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#71807a]">Ruta seleccionada</p>
                        <h3 class="mt-1 text-lg font-black text-[#227c70]">Lote {{ $selectedRoute->code }}</h3>
                        <p class="mt-1 text-xs text-[#71807a]">{{ $selectedRoute->driver_name }} · {{ $selectedRoute->vehicle_plate }} · {{ $selectedRoute->route_date->format('d/m/Y') }}</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        @if ($openSession)
                            <form method="POST" action="{{ route('cash-register.attach-invoice', $selectedRoute) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                <label class="grid gap-1 text-xs font-bold text-[#52635b]">
                                    Anexar factura existente
                                    <select name="sale_id" class="min-w-64 rounded-lg border-[#cbd6cf] text-sm" required>
                                        <option value="">Selecciona factura</option>
                                        @php foreach ($availableSales as $sale): @endphp
                                            <option value="{{ $sale->id }}">{{ $sale->invoice_number }} · {{ $sale->customer_name }} · ${{ number_format($sale->total, 2) }}</option>
                                        @php endforeach; @endphp
                                    </select>
                                </label>
                                <button class="rounded-lg border border-[#227c70] px-4 py-2.5 text-xs font-bold text-[#227c70]">Agregar factura</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('cash-register.reschedule', $selectedRoute) }}" class="flex items-end gap-2">
                            @csrf
                            @method('PATCH')
                            <label class="grid gap-1 text-xs font-bold text-[#52635b]">
                                Reprogramar toda la ruta
                                <input type="date" name="route_date" id="routeDate" value="{{ $selectedRoute->route_date->toDateString() }}" class="rounded-lg border-[#cbd6cf] text-sm" required>
                            </label>
                            <button class="rounded-lg border border-[#cbd6cf] px-3 py-2.5 text-xs font-bold text-[#52635b]">Guardar fecha</button>
                        </form>
                    </div>
                </section>
            @endif

            @if ($openSession)
                @if ($selectedRoute)
                <form action="{{ route('cash-register.settle-batch') }}" method="POST" id="settlementForm">
                    @csrf
                    <input type="hidden" name="route_id" value="{{ $selectedRoute->id }}">
                    <input type="hidden" name="session_id" value="{{ $openSession->id }}">
                    <input type="hidden" name="route_date" id="batchRouteDate" value="{{ $selectedRoute->route_date->toDateString() }}">
                    <section class="space-y-4 rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">Facturas del lote y formas de pago</h3>
                                <p class="mt-1 text-xs text-[#71807a]">Las devoluciones reponen inventario; quitar una factura solo la retira del lote.</p>
                            </div>
                            <button type="button" onclick="exportToExcel()" class="rounded-lg bg-[#192522] px-3 py-2 text-xs font-bold text-white">Exportar reporte</button>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-[#e6ede8]">
                            <table class="w-full min-w-[1050px] text-left text-xs" id="settlementTable">
                                <thead class="bg-[#192522] text-[10px] uppercase tracking-wider text-white">
                                    <tr>
                                        <th class="p-3">Factura / cliente / productos</th>
                                        <th class="p-3 text-right">Valor</th>
                                        <th class="p-3">Acción</th>
                                        <th class="p-3">Forma de cobro</th>
                                        <th class="p-3 text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6ede8] bg-white">
                                    @if ($deliveryStops->isNotEmpty())
                                        @php foreach ($deliveryStops as $stop): @endphp
                                        <tr class="align-top transition hover:bg-[#f4f8f5]">
                                            <td class="p-3 font-bold text-[#192522]">
                                                {{ $stop->sale?->invoice_number ?? 'Parada #'.$stop->id }}
                                                <span class="font-normal text-[#71807a]">· {{ $stop->sale?->customer_name ?? $stop->customer_name }}</span>
                                                @if ($stop->sale)
                                                    <details class="mt-2 font-normal">
                                                        <summary class="cursor-pointer text-[#227c70]">Productos y devolución parcial</summary>
                                                        <div class="mt-2 grid gap-2">
                                                            @php foreach ($stop->sale->details as $detail): @endphp
                                                                @php
                                                                    $returnedQty = (int) $stop->returns->where('sale_detail_id', $detail->id)->sum('quantity');
                                                                    $remainingQty = max(0, (int) $detail->quantity - $returnedQty);
                                                                @endphp
                                                                <label class="flex items-center justify-between gap-3 rounded-lg bg-[#f8faf8] p-2">
                                                                    <span>{{ $detail->item?->name }} · disponibles para devolución: {{ $remainingQty }}</span>
                                                                    <input type="number" name="stops[{{ $stop->id }}][return_quantities][{{ $detail->id }}]" min="0" max="{{ $remainingQty }}" value="0" data-unit-price="{{ $detail->unit_price }}" data-discount="{{ $detail->discount_percentage }}" data-tax="{{ $detail->iva_percentage }}" class="return-quantity w-20 rounded border-[#cbd6cf] text-xs" oninput="calculateTotals()">
                                                                </label>
                                                            @php endforeach; @endphp
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <select name="stops[{{ $stop->id }}][replacement_item_id]" onchange="calculateTotals()" class="max-w-64 rounded border-[#cbd6cf] text-xs">
                                                                    <option value="">Producto para cruce</option>
                                                                    @php foreach ($availableItems as $item): @endphp
                                                                        <option value="{{ $item->id }}" data-sale-price="{{ $item->sale_price }}">{{ $item->name }} · stock {{ $item->stock }} · ${{ number_format($item->sale_price, 2) }}</option>
                                                                    @php endforeach; @endphp
                                                                </select>
                                                                <input type="number" name="stops[{{ $stop->id }}][replacement_quantity]" min="1" value="1" class="replacement-quantity w-20 rounded border-[#cbd6cf] text-xs" aria-label="Cantidad del producto de reemplazo" oninput="calculateTotals()">
                                                            </div>
                                                            <p class="text-xs font-bold text-[#8b6811]">Diferencia estimada del cruce: <span class="exchange-difference">$0.00</span></p>
                                                        </div>
                                                    </details>
                                                @endif
                                            </td>
                                            <td class="p-3 text-right font-bold">${{ number_format($stop->sale?->total ?? 0, 2) }}</td>
                                            <td class="p-3">
                                                <select name="stops[{{ $stop->id }}][payment_action]" onchange="handleActionChange(this); calculateTotals()" class="settlement-type w-52 rounded-lg border-[#cbd6cf] text-xs font-bold" required>
                                                    @php foreach ($settlementActions as $key => $label): @endphp
                                                        <option value="{{ $key }}" @selected(($stop->payment_action ?? '1305') === $key)>{{ $label }}</option>
                                                    @php endforeach; @endphp
                                                </select>
                                            </td>
                                            <td class="p-3">
                                                <select name="stops[{{ $stop->id }}][collection_method]" onchange="calculateTotals()" class="settlement-method w-44 rounded-lg border-[#cbd6cf] text-xs">
                                                    <option value="">Usar acción elegida</option>
                                                    <option value="1105">1105 Efectivo</option>
                                                    <option value="1120">1120 Banco</option>
                                                </select>
                                            </td>
                                            <td class="p-3 text-right">
                                                <input type="number" step="0.01" min="0" name="stops[{{ $stop->id }}][amount_paid]" value="{{ $stop->collected_amount ?? 0 }}" oninput="calculateTotals()" class="settlement-amount w-32 rounded-lg border-[#cbd6cf] text-right text-xs font-bold">
                                            </td>
                                        </tr>
                                        @php endforeach; @endphp
                                    @else
                                        <tr><td colspan="5" class="p-6 text-center text-gray-500">Este lote todavía no tiene facturas.</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-2xl border border-[#e6ede8] bg-[#fbfcfb] p-4 sm:p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-black text-[#b65338]">Gastos y salidas de efectivo</h4>
                                    <p class="text-xs text-[#71807a]">Combustible, pagos a proveedores, traslados internos u otros.</p>
                                </div>
                                <button type="button" onclick="addExpenseRow()" class="rounded-lg bg-[#b65338] px-3 py-2 text-xs font-bold text-white">Agregar gasto</button>
                            </div>
                            <div class="mt-3 grid gap-2" id="expensesList">
                                @php foreach ($openSession->expenses as $index => $expense): @endphp
                                    <div class="expense-row grid gap-2 sm:grid-cols-[12rem_1fr_10rem_auto]">
                                        <select name="expenses[{{ $index }}][category]" class="expense-category rounded-lg border-[#cbd6cf] text-xs">
                                            <option value="combustible" @selected($expense->category === 'combustible')>Combustible</option>
                                            <option value="proveedor" @selected($expense->category === 'proveedor')>Pago a proveedor</option>
                                            <option value="transferencia" @selected($expense->category === 'transferencia')>Traslado interno a banco</option>
                                            <option value="otro" @selected($expense->category === 'otro')>Otro gasto</option>
                                        </select>
                                        <input type="text" name="expenses[{{ $index }}][concept]" value="{{ $expense->concept }}" placeholder="Concepto / proveedor" class="rounded-lg border-[#cbd6cf] text-xs" required>
                                        <input type="number" min="0.01" step="0.01" name="expenses[{{ $index }}][amount]" value="{{ $expense->amount }}" oninput="calculateTotals()" class="expense-amount rounded-lg border-[#cbd6cf] text-right text-xs" required>
                                        <button type="button" onclick="removeExpenseRow(this)" class="rounded-lg px-3 text-xs font-bold text-red-600">Quitar</button>
                                    </div>
                                @php endforeach; @endphp
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                            <div class="rounded-xl border-l-4 border-[#227c70] bg-white p-4 shadow-sm">
                                <p class="text-[10px] font-bold uppercase text-[#71807a]">Efectivo recibido</p>
                                <p class="text-lg font-black text-[#227c70]" id="lblTotalCash">$0.00</p>
                            </div>
                            <div class="rounded-xl border-l-4 border-blue-500 bg-white p-4 shadow-sm">
                                <p class="text-[10px] font-bold uppercase text-[#71807a]">Bancos / transferencias</p>
                                <p class="text-lg font-black text-blue-600" id="lblTotalBank">$0.00</p>
                            </div>
                            <div class="rounded-xl border-l-4 border-[#71807a] bg-white p-4 shadow-sm">
                                <p class="text-[10px] font-bold uppercase text-[#71807a]">Ventas a crédito</p>
                                <p class="text-lg font-black text-[#52635b]" id="lblTotalCredit">$0.00</p>
                            </div>
                            <div class="rounded-xl border-l-4 border-[#b65338] bg-white p-4 shadow-sm">
                                <p class="text-[10px] font-bold uppercase text-[#71807a]">Total gastos</p>
                                <p class="text-lg font-black text-[#b65338]" id="lblTotalExpenses">-$0.00</p>
                            </div>
                            <div class="rounded-xl border border-[#e2c044] border-l-4 bg-[#fff8df] p-4 shadow-sm sm:col-span-2 lg:col-span-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#8b6811]">Efectivo esperado</p>
                                <p class="mt-1 text-2xl font-black text-[#8b6811]" id="lblExpectedCash">$0.00</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-[#35443e] bg-[#192522] p-4 text-white shadow-sm sm:p-5">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#b9c9c1]">Diferencia frente al efectivo contado</p>
                                <p class="mt-1 text-xl font-black" id="lblDifference">$0.00</p>
                            </div>
                            <button type="submit" class="rounded-lg bg-[#227c70] px-6 py-2.5 text-xs font-bold uppercase text-white">Guardar liquidación del lote</button>
                        </div>
                    </section>
                </form>

                @endif

                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-black text-[#227c70]">Cobro de cartera</h3>
                    <p class="mt-1 text-xs text-[#71807a]">Registra el pago y la contabilización de una factura sin afectar inventario.</p>
                    <form method="POST" action="{{ route('cash-register.collect-receivable') }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="session_id" value="{{ $openSession->id }}">
                        <label class="grid gap-1 text-xs font-bold text-[#52635b]">
                            Factura
                            <select name="sale_id" class="min-w-64 rounded-lg border-[#cbd6cf] text-sm" required>
                                <option value="">Selecciona factura pendiente</option>
                                @php foreach ($receivableSales as $sale): @endphp
                                    <option value="{{ $sale->id }}">{{ $sale->invoice_number }} · {{ $sale->customer_name }} · ${{ number_format($sale->total, 2) }}</option>
                                @php endforeach; @endphp
                            </select>
                        </label>
                        <label class="grid gap-1 text-xs font-bold text-[#52635b]">
                            Valor recibido
                            <input type="number" min="0.01" step="0.01" name="amount" class="rounded-lg border-[#cbd6cf] text-sm" required>
                        </label>
                        <label class="grid gap-1 text-xs font-bold text-[#52635b]">
                            Cuenta de ingreso
                            <select name="payment_method" class="rounded-lg border-[#cbd6cf] text-sm" required>
                                <option value="1105">1105 Efectivo</option>
                                <option value="1120">1120 Banco</option>
                            </select>
                        </label>
                        <button class="rounded-lg bg-[#227c70] px-4 py-2.5 text-xs font-bold text-white">Registrar cartera</button>
                    </form>
                </section>

                @unless ($selectedRoute)
                    <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-black text-[#b65338]">Gasto de caja</h3>
                        <form method="POST" action="{{ route('cash-register.expenses.store', $openSession) }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <label class="grid gap-1 text-xs font-bold text-[#52635b]">Tipo de gasto
                                <select name="category" class="rounded-lg border-[#cbd6cf] text-sm">
                                    <option value="combustible">Combustible</option>
                                    <option value="proveedor">Pago a proveedor</option>
                                    <option value="transferencia">Traslado interno a banco</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </label>
                            <label class="grid gap-1 text-xs font-bold text-[#52635b]">Concepto
                                <input name="concept" maxlength="255" class="rounded-lg border-[#cbd6cf] text-sm" required>
                            </label>
                            <label class="grid gap-1 text-xs font-bold text-[#52635b]">Valor
                                <input type="number" min="0.01" step="0.01" name="amount" class="rounded-lg border-[#cbd6cf] text-sm" required>
                            </label>
                            <button class="rounded-lg bg-[#b65338] px-4 py-2.5 text-xs font-bold text-white">Registrar gasto</button>
                        </form>
                    </section>
                @endunless

                <form method="POST" action="{{ route('cash-register.close', $openSession) }}" class="rounded-2xl border border-[#e8d9b7] bg-white p-5 shadow-sm sm:p-6">
                    @csrf
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#b65338]">Cierre diario</p>
                            <h3 class="mt-1 text-base font-black text-[#192522]">Conteo físico de efectivo</h3>
                            <p class="mt-1 text-xs text-[#71807a]">Ingresa la cantidad de billetes y monedas para calcular el efectivo contado.</p>
                        </div>
                        <span class="rounded-full bg-[#fff4db] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-[#8b6811]">Verificación final</span>
                    </div>
                    @php
                        $denominationLabels = [
                            'bill_100000' => 'Billetes de $100.000',
                            'bill_50000' => 'Billetes de $50.000',
                            'bill_20000' => 'Billetes de $20.000',
                            'bill_10000' => 'Billetes de $10.000',
                            'bill_5000' => 'Billetes de $5.000',
                            'bill_2000' => 'Billetes de $2.000',
                            'bill_1000' => 'Billetes de $1.000',
                            'coin_1000' => 'Monedas de $1.000',
                            'coin_500' => 'Monedas de $500',
                            'coin_200' => 'Monedas de $200',
                            'coin_100' => 'Monedas de $100',
                            'coin_50' => 'Monedas de $50',
                        ];
                    @endphp
                    <section class="mt-5 rounded-2xl border border-[#d7dfd8] bg-[#f8faf8] p-4 sm:p-5" aria-labelledby="closing-summary-title">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#71807a]">Resumen de cierre</p>
                                <h4 id="closing-summary-title" class="mt-1 text-sm font-black text-[#192522]">Compara el efectivo esperado con el conteo físico</h4>
                            </div>
                            <span id="closeStatus" aria-live="polite" class="rounded-full bg-[#e9eeeb] px-3 py-1.5 text-xs font-bold text-[#52635b]">Por contar</span>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-[#e6ede8] bg-white p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#71807a]">Efectivo esperado</p>
                                <p class="mt-1 text-lg font-black text-[#192522]">${{ number_format($openSession->expected_cash, 2) }}</p>
                            </div>
                            <div class="rounded-xl border border-[#e6ede8] bg-white p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#71807a]">Efectivo contado</p>
                                <p id="closeCountedCash" class="mt-1 text-lg font-black text-[#192522]">$0.00</p>
                            </div>
                            <div class="rounded-xl border border-[#e6ede8] bg-white p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#71807a]">Diferencia</p>
                                <p id="closeDifference" class="mt-1 text-lg font-black text-[#52635b]">$0.00</p>
                            </div>
                        </div>
                    </section>
                    <div class="mt-5 space-y-4">
                        <div class="rounded-xl bg-[#f8faf8] p-4">
                            <p class="mb-3 text-[10px] font-bold uppercase tracking-wider text-[#71807a]">Billetes</p>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @php foreach (array_slice($denominationLabels, 0, 7, true) as $field => $label): @endphp
                                    <label class="grid gap-1 text-xs font-bold text-[#52635b]">{{ $label }}
                                        <input type="number" min="0" max="100000" name="{{ $field }}" value="{{ $openSession->{$field} ?? 0 }}" oninput="calculateDenominations(event)" class="denomination-count rounded-lg border-[#cbd6cf] text-sm">
                                    </label>
                                @php endforeach; @endphp
                            </div>
                        </div>
                        <div class="rounded-xl bg-[#f8faf8] p-4">
                            <p class="mb-3 text-[10px] font-bold uppercase tracking-wider text-[#71807a]">Monedas</p>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @php foreach (array_slice($denominationLabels, 7, null, true) as $field => $label): @endphp
                                    <label class="grid gap-1 text-xs font-bold text-[#52635b]">{{ $label }}
                                        <input type="number" min="0" max="100000" name="{{ $field }}" value="{{ $openSession->{$field} ?? 0 }}" oninput="calculateDenominations(event)" class="denomination-count rounded-lg border-[#cbd6cf] text-sm">
                                    </label>
                                @php endforeach; @endphp
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 rounded-2xl bg-[#192522] p-4 text-white sm:p-5">
                        <div>
                            <label for="countedCash" class="text-xs font-bold uppercase">Efectivo contado</label>
                            <input id="countedCash" type="number" step="0.01" min="0" name="counted_cash" value="{{ $openSession->counted_cash ?? 0 }}" class="ml-2 w-40 rounded-lg border-none text-sm font-black text-black" required>
                            <span class="ml-2 text-xs text-[#b9c9c1]">Suma denominaciones: <strong id="denominationTotal">$0.00</strong></span>
                        </div>
                        <label class="grid gap-1 text-xs font-bold uppercase">Notas
                            <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="rounded-lg border-none text-sm font-normal text-black">
                        </label>
                        <button class="rounded-lg bg-[#227c70] px-6 py-2.5 text-xs font-bold uppercase">Cerrar caja y generar arqueo</button>
                    </div>
                </form>
            @elseif ($selectedRoute)
                <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">Abre una sesión de caja para liquidar el lote.</p>
            @endif

            @if ($recentSessions->isNotEmpty())
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <div>
                        <h3 class="text-sm font-black text-[#192522]">Arqueos cerrados recientes</h3>
                        <p class="mt-1 text-xs text-[#71807a]">Consulta los últimos cierres registrados en caja.</p>
                    </div>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-[#e6ede8]">
                        <table class="w-full min-w-[640px] text-left text-xs">
                            <thead class="bg-[#f4f8f5] text-[10px] uppercase tracking-wider text-[#52635b]"><tr><th class="p-3">Fecha</th><th class="p-3">Base</th><th class="p-3">Esperado</th><th class="p-3">Contado</th><th class="p-3">Diferencia</th><th class="p-3">Gastos</th></tr></thead>
                            <tbody>
                                @php foreach ($recentSessions as $session): @endphp
                                    <tr class="border-b border-[#e6ede8] transition last:border-0 hover:bg-[#f8faf8]">
                                        <td class="whitespace-nowrap p-3">{{ $session->closed_at?->format('d/m/Y H:i') }}</td>
                                        <td class="p-3">${{ number_format($session->opening_balance, 2) }}</td>
                                        <td class="p-3 font-bold">${{ number_format($session->expected_cash, 2) }}</td>
                                        <td class="p-3">${{ number_format($session->counted_cash, 2) }}</td>
                                        <td class="p-3 font-bold">${{ number_format($session->difference, 2) }}</td>
                                        <td class="p-3">${{ number_format($session->cash_out, 2) }}</td>
                                    </tr>
                                @php endforeach; @endphp
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>

    <script>
        const denominationValues = {
            bill_100000: 100000, bill_50000: 50000, bill_20000: 20000, bill_10000: 10000,
            bill_5000: 5000, bill_2000: 2000, bill_1000: 1000, coin_1000: 1000,
            coin_500: 500, coin_200: 200, coin_100: 100, coin_50: 50
        };
        let expenseIndex = {{ $openSession?->expenses->count() ?? 0 }};
        let hasClosingCount = Number(document.getElementById('countedCash')?.value || 0) > 0
            || Array.from(document.querySelectorAll('.denomination-count')).some(input => Number(input.value || 0) > 0);

        function addExpenseRow() {
            const row = document.createElement('div');
            row.className = 'expense-row grid gap-2 sm:grid-cols-[12rem_1fr_10rem_auto]';
            row.innerHTML = `
                <select name="expenses[${expenseIndex}][category]" class="expense-category rounded-lg border-[#cbd6cf] text-xs">
                    <option value="combustible">Combustible</option><option value="proveedor">Pago a proveedor</option>
                    <option value="transferencia">Traslado interno a banco</option><option value="otro">Otro gasto</option>
                </select>
                <input type="text" name="expenses[${expenseIndex}][concept]" placeholder="Concepto / proveedor" class="rounded-lg border-[#cbd6cf] text-xs" required>
                <input type="number" min="0.01" step="0.01" name="expenses[${expenseIndex}][amount]" oninput="calculateTotals()" placeholder="0.00" class="expense-amount rounded-lg border-[#cbd6cf] text-right text-xs" required>
                <button type="button" onclick="removeExpenseRow(this)" class="rounded-lg px-3 text-xs font-bold text-red-600">Quitar</button>`;
            document.getElementById('expensesList').appendChild(row);
            expenseIndex++;
        }

        function handleActionChange(select) {
            if (select.value === 'reschedule') {
                document.getElementById('routeDate')?.focus();
            }
        }

        function removeExpenseRow(button) {
            button.closest('.expense-row').remove();
            calculateTotals();
        }

        function calculateTotals() {
            if (!document.getElementById('lblTotalCash')) return;
            let cash = 0;
            let bank = 0;
            let credit = 0;
            let expenses = 0;
            document.querySelectorAll('#settlementTable tbody tr').forEach(row => {
                const action = row.querySelector('.settlement-type')?.value;
                const amount = Number(row.querySelector('.settlement-amount')?.value || 0);
                const collectionMethod = row.querySelector('.settlement-method')?.value;
                if (action === '1105' || (action === 'collection' && collectionMethod === '1105')) {
                    cash += amount;
                } else if (action === '1120' || (action === 'collection' && collectionMethod === '1120')) {
                    bank += amount;
                } else if (action === '1305') {
                    credit += Number(row.cells[1]?.innerText.replace(/[^\d,.-]/g, '').replaceAll(',', '') || 0);
                } else if (action === 'exchange') {
                    let returnedValue = 0;
                    row.querySelectorAll('.return-quantity').forEach(input => {
                        const discount = Number(input.dataset.discount || 0) / 100;
                        const tax = Number(input.dataset.tax || 0) / 100;
                        returnedValue += Number(input.value || 0) * Number(input.dataset.unitPrice || 0) * (1 - discount) * (1 + tax);
                    });
                    const replacementSelect = row.querySelector('[name$="[replacement_item_id]"]');
                    const replacementPrice = Number(replacementSelect?.selectedOptions[0]?.dataset.salePrice || 0);
                    const replacementQuantity = Number(row.querySelector('.replacement-quantity')?.value || 0);
                    const difference = replacementPrice * replacementQuantity - returnedValue;
                    const differenceLabel = row.querySelector('.exchange-difference');
                    if (differenceLabel) differenceLabel.innerText = (difference < 0 ? '-$' : '$') + Math.abs(difference).toFixed(2);
                    if (difference > 0 && collectionMethod === '1105') cash += difference;
                    if (difference > 0 && collectionMethod === '1120') bank += difference;
                    if (difference < 0 && collectionMethod === '1105') cash += difference;
                }
            });
            document.querySelectorAll('.expense-amount').forEach(input => expenses += Number(input.value || 0));
            const base = Number(@json($openSession?->opening_balance ?? 0));
            const expected = base + cash - expenses;
            document.getElementById('lblTotalCash').innerText = '$' + cash.toFixed(2);
            document.getElementById('lblTotalBank').innerText = '$' + bank.toFixed(2);
            document.getElementById('lblTotalCredit').innerText = '$' + credit.toFixed(2);
            document.getElementById('lblTotalExpenses').innerText = '-$' + expenses.toFixed(2);
            document.getElementById('lblExpectedCash').innerText = '$' + expected.toFixed(2);
            const counted = Number(document.getElementById('countedCash')?.value || 0);
            document.getElementById('lblDifference').innerText = (counted - expected >= 0 ? '+' : '') + '$' + (counted - expected).toFixed(2);
        }

        function updateClosingSummary() {
            const countedInput = document.getElementById('countedCash');
            const countedLabel = document.getElementById('closeCountedCash');
            const differenceLabel = document.getElementById('closeDifference');
            const statusLabel = document.getElementById('closeStatus');
            if (!countedInput || !countedLabel || !differenceLabel || !statusLabel) return;

            const expected = Number(@json($openSession?->expected_cash ?? 0));
            const counted = Number(countedInput.value || 0);
            const difference = counted - expected;
            const absoluteDifference = '$' + Math.abs(difference).toFixed(2);
            countedLabel.innerText = '$' + counted.toFixed(2);

            if (!hasClosingCount) {
                differenceLabel.innerText = '$0.00';
                differenceLabel.className = 'mt-1 text-lg font-black text-[#52635b]';
                statusLabel.innerText = 'Por contar';
                statusLabel.className = 'rounded-full bg-[#e9eeeb] px-3 py-1.5 text-xs font-bold text-[#52635b]';
            } else if (Math.abs(difference) < 0.01) {
                differenceLabel.innerText = '$0.00';
                differenceLabel.className = 'mt-1 text-lg font-black text-emerald-700';
                statusLabel.innerText = 'Caja cuadrada';
                statusLabel.className = 'rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-800';
            } else if (difference < 0) {
                differenceLabel.innerText = '-' + absoluteDifference;
                differenceLabel.className = 'mt-1 text-lg font-black text-red-700';
                statusLabel.innerText = 'Faltante de ' + absoluteDifference;
                statusLabel.className = 'rounded-full bg-red-100 px-3 py-1.5 text-xs font-bold text-red-800';
            } else {
                differenceLabel.innerText = '+' + absoluteDifference;
                differenceLabel.className = 'mt-1 text-lg font-black text-amber-700';
                statusLabel.innerText = 'Sobrante de ' + absoluteDifference;
                statusLabel.className = 'rounded-full bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800';
            }
        }

        function calculateDenominations(event) {
            if (!document.getElementById('denominationTotal')) return;
            if (event) {
                hasClosingCount = true;
            }
            let total = 0;
            Object.entries(denominationValues).forEach(([field, amount]) => {
                const count = Number(document.querySelector(`[name="${field}"]`)?.value || 0);
                total += count * amount;
            });
            document.getElementById('denominationTotal').innerText = '$' + total.toFixed(2);
            if (Object.keys(denominationValues).some(field => Number(document.querySelector(`[name="${field}"]`)?.value || 0) > 0)) {
                document.getElementById('countedCash').value = total.toFixed(2);
            }
            updateClosingSummary();
            calculateTotals();
        }

        function exportToExcel() {
            const rows = [['Factura / Cliente', 'Valor', 'Acción', 'Monto recibido']];
            document.querySelectorAll('#settlementTable tbody tr').forEach(row => {
                if (!row.querySelector('.settlement-type')) return;
                rows.push([
                    row.cells[0].innerText.replace(/\n/g, ' - '),
                    row.cells[1].innerText,
                    row.querySelector('.settlement-type').selectedOptions[0].text,
                    row.querySelector('.settlement-amount').value
                ]);
            });
            rows.push(['Total efectivo', document.getElementById('lblTotalCash').innerText]);
            rows.push(['Total bancos', document.getElementById('lblTotalBank').innerText]);
            rows.push(['Total gastos', document.getElementById('lblTotalExpenses').innerText]);
            rows.push(['Efectivo esperado', document.getElementById('lblExpectedCash').innerText]);
            const csv = rows.map(row => row.map(value => `"${String(value ?? '').replaceAll('"', '""')}"`).join(';')).join('\n');
            const link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' }));
            link.download = `arqueo_${new Date().toISOString().slice(0, 10)}.csv`;
            link.click();
            URL.revokeObjectURL(link.href);
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('routeDate')?.addEventListener('input', event => {
                const batchDate = document.getElementById('batchRouteDate');
                if (batchDate) batchDate.value = event.target.value;
            });
            calculateTotals();
            calculateDenominations();
            document.getElementById('countedCash')?.addEventListener('input', () => {
                hasClosingCount = true;
                updateClosingSummary();
                calculateTotals();
            });
        });
    </script>
</x-app-layout>
