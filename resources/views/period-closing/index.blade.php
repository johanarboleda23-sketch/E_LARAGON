<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Contabilidad</p>
                <h2 class="mt-1 text-xl font-black text-slate-900">Cierres de mes y año</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Volver al tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-7xl space-y-5">
            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errors->first() }}</div>
            @endif

            <section class="flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Cierre contable {{ $year }}</h1>
                    <p class="mt-1 max-w-3xl text-sm text-slate-600">El cierre bloquea compras, recibos/ingresos, egresos y conciliación bancaria. Los informes de costos se calculan con las compras del periodo. Ventas y nómina permanecen fuera del bloqueo, según el alcance elegido.</p>
                </div>
                <form method="GET" action="{{ route('period-closing.index') }}" class="flex items-end gap-2">
                    <label class="grid gap-1 text-xs font-semibold text-slate-600">Año
                        <input type="number" name="year" min="2000" max="2200" value="{{ $year }}" class="w-28 rounded-lg border-slate-300 text-sm">
                    </label>
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-bold text-white">Consultar</button>
                </form>
            </section>

            @if ($detail && $detailRecords)
                <section class="rounded-2xl border border-sky-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('period-closing.index', ['year' => $year]) }}" class="text-sm font-semibold text-sky-700">&larr; Volver al resumen</a>
                            <h2 class="mt-2 text-lg font-black text-slate-900">{{ $detailLabel }} — {{ \Illuminate\Support\Carbon::create($year, (int) $month, 1)->locale('es')->monthName }} {{ $year }}</h2>
                        </div>
                        @if ($detail === 'costs')
                            <a href="{{ route('costs.index', ['year' => $year, 'month' => $month]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">Abrir informe de costos</a>
                        @endif
                    </div>
                    @if ($detail === 'purchases')
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-100"><tr><th class="p-3 text-left">Fecha</th><th class="p-3 text-left">Factura</th><th class="p-3 text-left">Proveedor</th><th class="p-3 text-right">Total</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($detailRecords as $purchase)
                                        <tr><td class="p-3">{{ $purchase->purchase_date->format('d/m/Y') }}</td><td class="p-3">{{ $purchase->invoice_number }}</td><td class="p-3">{{ $purchase->provider }}</td><td class="p-3 text-right">${{ number_format($purchase->total_pagar, 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="4" class="p-5 text-center text-slate-500">No hay compras en este mes.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif (in_array($detail, ['income', 'expenses'], true))
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-100"><tr><th class="p-3 text-left">Fecha</th><th class="p-3 text-left">Comprobante</th><th class="p-3 text-left">Tercero</th><th class="p-3 text-left">Descripción</th><th class="p-3 text-right">Total</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($detailRecords as $voucher)
                                        <tr><td class="p-3">{{ $voucher->voucher_date->format('d/m/Y') }}</td><td class="p-3">{{ $voucher->consecutive }}</td><td class="p-3">{{ $voucher->third_party }}</td><td class="p-3">{{ $voucher->description }}</td><td class="p-3 text-right">${{ number_format($voucher->total_debit, 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="5" class="p-5 text-center text-slate-500">No hay comprobantes en este mes.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <h3 class="mt-6 font-bold text-slate-900">Abonos registrados directamente</h3>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-100"><tr><th class="p-3 text-left">Fecha</th><th class="p-3 text-left">Documento</th><th class="p-3 text-left">Tercero</th><th class="p-3 text-left">Método</th><th class="p-3 text-right">Valor</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($paymentRecords as $payment)
                                        <tr>
                                            <td class="p-3">{{ $payment->payment_date->format('d/m/Y') }}</td>
                                            <td class="p-3">{{ $payment->payable?->invoice_number ?? $payment->payable?->consecutive ?? 'Abono' }}</td>
                                            <td class="p-3">{{ $payment->payable?->customer_name ?? $payment->payable?->provider ?? $payment->payable?->supplier?->name ?? '—' }}</td>
                                            <td class="p-3">{{ $payment->method }}</td>
                                            <td class="p-3 text-right">${{ number_format($payment->amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="p-5 text-center text-slate-500">No hay abonos directos adicionales en este mes.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $paymentRecords->links() }}</div>
                    @elseif ($detail === 'bank')
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-100"><tr><th class="p-3 text-left">Fecha</th><th class="p-3 text-left">Banco</th><th class="p-3 text-left">Referencia</th><th class="p-3 text-left">Movimiento</th><th class="p-3 text-right">Importe</th><th class="p-3 text-left">Estado</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($detailRecords as $statement)
                                        <tr><td class="p-3">{{ $statement->transaction_date->format('d/m/Y') }}</td><td class="p-3">{{ $statement->paymentMethod?->name ?? 'Sin cuenta asignada' }}</td><td class="p-3">{{ $statement->reference ?: $statement->description }}</td><td class="p-3">{{ $statement->transaction_type === 'credit' ? 'Ingreso' : 'Egreso' }}</td><td class="p-3 text-right">${{ number_format($statement->amount, 2) }}</td><td class="p-3">{{ $statement->status === 'reconciled' ? 'Conciliado' : 'Pendiente' }}</td></tr>
                                    @empty
                                        <tr><td colspan="6" class="p-5 text-center text-slate-500">No hay movimientos bancarios en este mes.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-100"><tr><th class="p-3 text-left">Fecha</th><th class="p-3 text-left">Producto</th><th class="p-3 text-left">Factura</th><th class="p-3 text-right">Cantidad</th><th class="p-3 text-right">Costo unitario</th><th class="p-3 text-right">Costo total</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($detailRecords as $line)
                                        <tr><td class="p-3">{{ $line->purchase->purchase_date->format('d/m/Y') }}</td><td class="p-3">{{ $line->item?->name ?? 'Producto eliminado' }}</td><td class="p-3">{{ $line->purchase->invoice_number }}</td><td class="p-3 text-right">{{ number_format($line->quantity, 4) }}</td><td class="p-3 text-right">${{ number_format($line->cost_price, 2) }}</td><td class="p-3 text-right">${{ number_format($line->quantity * $line->cost_price, 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="6" class="p-5 text-center text-slate-500">No hay costos de producto en este mes.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <div class="mt-4">{{ $detailRecords->links() }}</div>
                </section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Resumen anual de los módulos incluidos</h2>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach (['purchases' => 'Compras', 'income' => 'Ingresos', 'expenses' => 'Egresos', 'bank' => 'Conciliación bancaria', 'costs' => 'Costos de productos'] as $key => $label)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p>
                            <strong class="mt-1 block text-lg text-slate-900">${{ number_format($yearSummary[$key]['total'], 2) }}</strong>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-slate-500">El cierre anual exige que los doce meses estén cerrados. No genera asiento contable automático y no bloquea módulos que quedaron fuera del alcance acordado.</p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    @if ($yearPeriod?->isClosed())
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-800">Año cerrado</span>
                        @if ($isAdministrator)
                            <form method="POST" action="{{ route('period-closing.reopen', $yearPeriod) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                <label class="grid gap-1 text-xs font-semibold text-slate-600">Motivo para reabrir
                                    <input type="text" name="reason" minlength="5" maxlength="1000" required class="w-72 rounded-lg border-slate-300 text-sm" placeholder="Explica el ajuste que se debe realizar">
                                </label>
                                <button class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-bold text-amber-800">Reabrir año</button>
                            </form>
                        @endif
                    @else
                        <form method="POST" action="{{ route('period-closing.close-year') }}">
                            @csrf
                            <input type="hidden" name="year" value="{{ $year }}">
                            <button @disabled(! $allMonthsClosed || \Illuminate\Support\Carbon::create($year, 12, 31)->isFuture()) class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">Cerrar año</button>
                        </form>
                        @unless ($allMonthsClosed)
                            <span class="text-sm text-amber-700">Cierra primero los doce meses.</span>
                        @endunless
                    @endif
                </div>
                @if ($yearPeriod?->events->isNotEmpty())
                    <details class="mt-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-slate-700">Ver auditoría del cierre anual</summary>
                        <ul class="mt-2 grid gap-2">
                            @foreach ($yearPeriod->events as $event)
                                <li class="rounded-lg bg-slate-50 p-3">{{ $event->action === 'close' ? 'Cerrado' : 'Reabierto' }} el {{ $event->created_at->format('d/m/Y H:i') }} por {{ $event->actor?->name ?? 'Usuario eliminado' }}@if($event->reason) — Motivo: {{ $event->reason }}@endif</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>

            <section class="space-y-3">
                @foreach ($months as $monthData)
                    @php($period = $monthData['period'])
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-black uppercase text-slate-900">{{ $monthData['name'] }} {{ $year }}</h3>
                                    @if ($period?->isClosed())
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Cerrado</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Abierto</span>
                                    @endif
                                </div>
                                @if ($period?->isClosed())
                                    <p class="mt-1 text-xs text-slate-500">Cerrado {{ $period->closed_at?->format('d/m/Y H:i') }} por {{ $period->closedBy?->name ?? 'Usuario eliminado' }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($period?->isClosed())
                                    @if ($isAdministrator)
                                        <form method="POST" action="{{ route('period-closing.reopen', $period) }}" class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            <input type="text" name="reason" minlength="5" maxlength="1000" required class="w-64 rounded-lg border-slate-300 text-sm" placeholder="Motivo obligatorio para reabrir">
                                            <button class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-bold text-amber-800">Abrir / editar</button>
                                        </form>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('period-closing.close-month') }}">
                                        @csrf
                                        <input type="hidden" name="year" value="{{ $year }}">
                                        <input type="hidden" name="month" value="{{ $monthData['number'] }}">
                                        <button @disabled(! $monthData['can_close'] || $monthData['readiness']['pending_bank_movements'] > 0 || $monthData['readiness']['open_cash_sessions'] > 0) class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">Cerrar mes</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($monthData['readiness']['pending_bank_movements'] > 0 || $monthData['readiness']['open_cash_sessions'] > 0)
                            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                @if ($monthData['readiness']['pending_bank_movements'] > 0)Hay {{ $monthData['readiness']['pending_bank_movements'] }} movimiento(s) bancario(s) pendiente(s) de conciliar. @endif
                                @if ($monthData['readiness']['open_cash_sessions'] > 0)Hay {{ $monthData['readiness']['open_cash_sessions'] }} sesión(es) de caja abierta(s). @endif
                                Resuelve estos pendientes para habilitar el cierre.
                            </p>
                        @endif

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            @foreach ([
                                'purchases' => ['Compras', 'Facturas de compras'],
                                'income' => ['Ingresos', 'Recibos de caja'],
                                'expenses' => ['Egresos', 'Egresos y gastos'],
                                'bank' => ['Bancos', 'Movimientos bancarios'],
                                'costs' => ['Costos', 'Costos de productos'],
                            ] as $key => [$label, $detailTitle])
                                <details @if($detail && (int) $month === $monthData['number']) open @endif class="rounded-xl border border-slate-200 p-3">
                                    <summary class="cursor-pointer list-none">
                                        <span class="block text-xs font-semibold uppercase text-slate-500">{{ $label }}</span>
                                        <strong class="mt-1 block text-sm text-slate-900">${{ number_format($monthData['summary'][$key]['total'], 2) }}</strong>
                                        <span class="text-xs text-slate-500">{{ $monthData['summary'][$key]['count'] }} {{ $key === 'costs' ? 'productos' : 'registros' }} · Ver detalle</span>
                                    </summary>
                                    <a class="mt-3 inline-flex text-xs font-bold text-sky-700 hover:text-sky-900" href="{{ route('period-closing.index', ['year' => $year, 'month' => $monthData['number'], 'detail' => $key]) }}">Desplegar {{ strtolower($detailTitle) }} del mes</a>
                                </details>
                            @endforeach
                        </div>

                        @if ($period?->events->isNotEmpty())
                            <details class="mt-3 text-xs text-slate-600">
                                <summary class="cursor-pointer font-semibold">Auditoría del periodo</summary>
                                <ul class="mt-2 grid gap-1">
                                    @foreach ($period->events as $event)
                                        <li>{{ $event->action === 'close' ? 'Cerrado' : 'Reabierto' }} el {{ $event->created_at->format('d/m/Y H:i') }} por {{ $event->actor?->name ?? 'Usuario eliminado' }}@if($event->reason) — {{ $event->reason }}@endif</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </article>
                @endforeach
            </section>
        </div>
    </div>
</x-app-layout>
