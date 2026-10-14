<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Tesorería & Logística</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Liquidación de Caja y Lotes de Rutas</h2>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="exportToExcel()" class="rounded-lg bg-[#227c70] px-3 py-2 text-xs font-bold text-white hover:bg-[#1a5f55]">
                    📊 Exportar a Excel
                </button>
                <button type="button" onclick="window.print()" class="rounded-lg bg-[#192522] px-3 py-2 text-xs font-bold text-white hover:bg-black">
                    🖨️ Imprimir / PDF
                </button>
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-6xl space-y-6">

            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
            @endif

            <!-- 1. CAPTURAR LOTE DE RUTAS -->
            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">📥 Capturar Lote de Rutas</h3>
                    <p class="text-xs text-[#71807a]">Selecciona el lote o ruta programada para cargar los clientes y facturas a liquidar.</p>
                </div>
                <form method="GET" action="{{ route('cash-register.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="fund_type" value="{{ $fundType }}">
                    <select name="route_id" class="rounded-lg border-[#cbd6cf] text-xs font-bold text-[#192522] focus:ring-[#227c70]">
                        <option value="">-- Seleccionar Lote / Ruta --</option>
                        @foreach($routes as$route)
                            <option value="{{ $route->id }}" {{ (string)$selectedRouteId === (string)$route->id ? 'selected' : '' }}>
                                Ruta #{{ $route->id }} - {{$route->driver_name ?? 'Sin Conductor' }} ({{ $route->route_date }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-[#227c70] px-4 py-2 text-xs font-bold text-white hover:bg-[#1a5f55]">
                        Capturar Lote
                    </button>
                </form>
            </section>

            <!-- FORMULARIO PRINCIPAL DE LIQUIDACIÓN -->
            <form action="{{ route('cash-register.settle-batch') }}" method="POST" id="settlementForm">
                @csrf

                <!-- 2. TABLA DE FACTURAS Y DESPLEGABLES -->
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm space-y-4 mb-6">
                    <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">📑 Facturas por Cliente y Formas de Pago</h3>
                    <div class="overflow-x-auto rounded-xl border border-[#e6ede8]">
                        <table class="w-full text-left text-xs" id="settlementTable">
                            <thead class="bg-[#192522] uppercase text-white">
                                <tr>
                                    <th class="p-3">Factura / Cliente</th>
                                    <th class="p-3 text-right">Valor Facturado</th>
                                    <th class="p-3">Tipo de Liquidación</th>
                                    <th class="p-3 text-right">Monto Recaudado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6ede8] bg-white">
                                @forelse($deliveryStops as$stop)
                                    <tr class="hover:bg-[#f8faf8]">
                                        <td class="p-3 font-bold text-[#192522]">
                                            {{ $stop->sale?->invoice_number ?? 'Parada #'.$stop->id }}<br>
                                            <span class="text-[11px] font-normal text-[#71807a]">{{ $stop->sale?->customer?->name ?? 'Cliente General' }}</span>
                                        </td>
                                        <td class="p-3 text-right font-bold text-[#192522]">
                                            ${{ number_format($stop->sale?->total ?? 0, 2) }}
                                        </td>
                                        <td class="p-3">
                                            <select name="stops[{{ $stop->id }}][payment_action]" onchange="calculateTotals()" class="settlement-type w-full rounded-lg border-[#cbd6cf] text-xs font-bold">
                                                @foreach($settlementActions as $key =>$label)
                                                    <option value="{{ $key }}" {{ $stop->payment_action === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="p-3 text-right">
                                            <input type="number" step="0.01" name="stops[{{ $stop->id }}][amount_paid]" 
                                                   value="{{ $stop->collected_amount ?? $stop->sale?->total ?? 0 }}" 
                                                   oninput="calculateTotals()" 
                                                   class="settlement-amount w-28 text-right rounded-lg border-[#cbd6cf] text-xs font-bold">
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="p-4 text-center text-gray-500">No hay facturas cargadas. Selecciona un lote de ruta arriba.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- 3. CUADRO DE GASTOS DINÁMICOS -->
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm space-y-4 mb-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black uppercase tracking-wider text-[#b65338]">💸 Registro de Gastos del Día</h3>
                        <button type="button" onclick="addExpenseRow()" class="rounded-lg bg-[#b65338] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#9c4630]">
                            ➕ Agregar Fila de Gasto
                        </button>
                    </div>
                    <table class="w-full text-left text-xs" id="expensesTable">
                        <thead class="bg-[#f8faf8] uppercase text-[#71807a]">
                            <tr>
                                <th class="p-2">Concepto / Descripción del Gasto</th>
                                <th class="p-2 text-right">Valor</th>
                                <th class="p-2 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e6ede8]">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </section>

                <!-- 4. RESUMEN Y CÁLCULO DE DIFERENCIA EN TIEMPO REAL -->
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm space-y-4">
                    <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">📊 Resumen de Liquidación de Caja</h3>
                    
                    <div class="grid gap-4 sm:grid-cols-4">
                        <div class="rounded-xl bg-[#f8faf8] p-4 border border-[#cbd6cf]">
                            <p class="text-[10px] font-bold uppercase text-[#71807a]">Total Recaudado Efectivo</p>
                            <p class="text-lg font-black text-[#227c70]" id="lblTotalCash">$0.00</p>
                        </div>
                        <div class="rounded-xl bg-[#f8faf8] p-4 border border-[#cbd6cf]">
                            <p class="text-[10px] font-bold uppercase text-[#71807a]">Recaudado Bancos / Transf.</p>
                            <p class="text-lg font-black text-blue-600" id="lblTotalBank">$0.00</p>
                        </div>
                        <div class="rounded-xl bg-[#f8faf8] p-4 border border-[#cbd6cf]">
                            <p class="text-[10px] font-bold uppercase text-[#71807a]">Menos Gastos</p>
                            <p class="text-lg font-black text-[#b65338]" id="lblTotalExpenses">-$0.00</p>
                        </div>
                        <div class="rounded-xl bg-[#fff8df] p-4 border border-[#e2c044]">
                            <p class="text-[10px] font-bold uppercase text-[#8b6811]">EFECTIVO ESPERADO EN CAJA</p>
                            <p class="text-xl font-black text-[#8b6811]" id="lblExpectedCash">$0.00</p>
                        </div>
                    </div>

                    <!-- RECUADRO DE CONTEO Y DIFERENCIA -->
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-[#192522] p-4 text-white">
                        <div class="flex items-center gap-3">
                            <label class="text-xs font-bold uppercase">Efectivo Físico Contado:</label>
                            <input type="number" step="0.01" id="inputCountedCash" oninput="calculateTotals()" placeholder="0.00" class="w-36 rounded-lg border-none text-black font-black text-sm">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase">Diferencia:</span>
                            <span id="lblDifference" class="rounded-lg px-4 py-2 text-base font-black bg-emerald-500 text-white">$0.00</span>
                        </div>
                        <button type="submit" class="rounded-lg bg-[#227c70] px-6 py-2.5 text-xs font-bold uppercase text-white hover:bg-[#1a5f55]">
                            💾 Guardar Liquidación
                        </button>
                    </div>
                </section>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT DE CÁLCULO EN TIEMPO REAL Y EXPORTACIÓN A EXCEL -->
    <script>
        let expenseIndex = 0;

        function addExpenseRow() {
            const table = document.getElementById('expensesTable').querySelector('tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="p-2"><input type="text" name="expenses[${expenseIndex}][concept]" placeholder="Escribe el concepto del gasto..." class="w-full rounded-lg border-[#cbd6cf] text-xs" required></td>
                <td class="p-2 text-right"><input type="number" step="0.01" min="0.01" name="expenses[${expenseIndex}][amount]" oninput="calculateTotals()" placeholder="0.00" class="expense-amount w-28 text-right rounded-lg border-[#cbd6cf] text-xs font-bold" required></td>
                <td class="p-2 text-center"><button type="button" onclick="removeExpenseRow(this)" class="text-red-600 font-bold hover:underline">X</button></td>
            `;
            table.appendChild(row);
            expenseIndex++;
        }

        function removeExpenseRow(btn) {
            btn.closest('tr').remove();
            calculateTotals();
        }

        function calculateTotals() {
            let totalCash = 0;
            let totalBank = 0;
            let totalExpenses = 0;

            // Filtrar únicamente las filas del settlementTable
            document.querySelectorAll('#settlementTable tbody tr').forEach(row => {
                const type = row.querySelector('.settlement-type')?.value;
                const amount = parseFloat(row.querySelector('.settlement-amount')?.value || 0);

                if (type === 'cash' || type === 'collection') {
                    totalCash += amount;
                } else if (type === 'bank') {
                    totalBank += amount;
                }
            });

            document.querySelectorAll('.expense-amount').forEach(input => {
                totalExpenses += parseFloat(input.value || 0);
            });

            const expectedCash = totalCash - totalExpenses;
            const countedCash = parseFloat(document.getElementById('inputCountedCash').value || 0);
            const difference = countedCash - expectedCash;

            document.getElementById('lblTotalCash').innerText = '$' + totalCash.toFixed(2);
            document.getElementById('lblTotalBank').innerText = '$' + totalBank.toFixed(2);
            document.getElementById('lblTotalExpenses').innerText = '-$' + totalExpenses.toFixed(2);
            document.getElementById('lblExpectedCash').innerText = '$' + expectedCash.toFixed(2);

            const lblDiff = document.getElementById('lblDifference');
            lblDiff.innerText = (difference >= 0 ? '+' : '') + '$' + difference.toFixed(2);

            if (Math.abs(difference) < 0.01) {
                lblDiff.className = 'rounded-lg px-4 py-2 text-base font-black bg-emerald-500 text-white';
            } else if (difference < 0) {
                lblDiff.className = 'rounded-lg px-4 py-2 text-base font-black bg-red-600 text-white';
            } else {
                lblDiff.className = 'rounded-lg px-4 py-2 text-base font-black bg-blue-600 text-white';
            }
        }

        function exportToExcel() {
            let html = '<table border="1"><thead><tr><th colspan="4" style="background-color: #192522; color: white; font-size: 16px;">LIQUIDACION DE CAJA Y RUTA</th></tr>';
            html += '<tr><th>Factura / Cliente</th><th>Valor Facturado</th><th>Forma de Pago / Novedad</th><th>Monto Recaudado</th></tr></thead><tbody>';

            document.querySelectorAll('#settlementTable tbody tr').forEach(row => {
                const client = row.cells[0]?.innerText.replace(/\n/g, ' - ') || '';
                const val = row.cells[1]?.innerText || '';
                const select = row.querySelector('.settlement-type');
                const typeText = select ? select.options[select.selectedIndex].text : '';
                const paid = row.querySelector('.settlement-amount')?.value || '0.00';

                html += `<tr><td>${client}</td><td>${val}</td><td>${typeText}</td><td>$${paid}</td></tr>`;
            });

            html += '<tr><td colspan="4"></td></tr><tr><th colspan="4" style="background-color: #b65338; color: white;">GASTOS REGISTRADOS</th></tr>';

            document.querySelectorAll('#expensesTable tbody tr').forEach(row => {
                const concept = row.querySelector('input[type="text"]')?.value || '';
                const amount = row.querySelector('.expense-amount')?.value || '0.00';
                if (concept !== '') {
                    html += `<tr><td colspan="3">${concept}</td><td>$${amount}</td></tr>`;
                }
            });

            html += `<tr><td colspan="4"></td></tr>
                <tr><th colspan="3">TOTAL EFECTIVO RECAUDADO</th><td>${document.getElementById('lblTotalCash').innerText}</td></tr>
                <tr><th colspan="3">RECAUDADO BANCOS</th><td>${document.getElementById('lblTotalBank').innerText}</td></tr>
                <tr><th colspan="3">MENOS GASTOS</th><td>${document.getElementById('lblTotalExpenses').innerText}</td></tr>
                <tr><th colspan="3" style="background-color: #fff8df;">EFECTIVO ESPERADO EN CAJA</th><td style="background-color: #fff8df; font-weight: bold;">${document.getElementById('lblExpectedCash').innerText}</td></tr>
                <tr><th colspan="3">EFECTIVO CONTADO</th><td>$${document.getElementById('inputCountedCash').value || '0.00'}</td></tr>
                <tr><th colspan="3">DIFERENCIA</th><td style="font-weight: bold;">${document.getElementById('lblDifference').innerText}</td></tr>`;

            html += '</tbody></table>';

            const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Liquidacion_Caja_${new Date().toISOString().slice(0, 10)}.xls`;
            link.click();
        }

        document.addEventListener('DOMContentLoaded', calculateTotals);
    </script>
</x-app-layout>