<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Contabilidad / Saldos iniciales</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Cartera importada</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('accounting.vouchers.index.typed', 'saldos_iniciales') }}" class="rounded-xl border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70] hover:bg-[#f3f5f1]">Comprobante de saldos iniciales</a>
                <a href="{{ route('accounting.index') }}" class="rounded-xl border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70] hover:bg-[#f3f5f1]">Contabilidad</a>
            </div>
        </div>
    </x-slot>

    <main class="min-h-[calc(100vh-65px)] bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-7xl space-y-5">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('accounting.opening-balances.index') }}" class="grid gap-3 sm:grid-cols-[1fr_220px_auto_auto] sm:items-end">
                    <label class="grid gap-1 text-xs font-semibold text-[#596760]">
                        Buscar tercero, documento, factura o CUFE
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="rounded-lg border-gray-300 text-sm" placeholder="Escribe para buscar">
                    </label>
                    <label class="grid gap-1 text-xs font-semibold text-[#596760]">
                        Tipo de cartera
                        <select name="type" class="rounded-lg border-gray-300 text-sm">
                            <option value="">Todos</option>
                            <option value="cliente" @selected(($filters['type'] ?? '') === 'cliente')>Clientes</option>
                            <option value="proveedor" @selected(($filters['type'] ?? '') === 'proveedor')>Proveedores</option>
                        </select>
                    </label>
                    <button type="submit" class="rounded-lg bg-[#227c70] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1a5f55]">Buscar</button>
                    <a href="{{ route('accounting.opening-balances.index') }}" class="rounded-lg border border-[#d7dfd8] px-4 py-2.5 text-center text-sm font-bold text-[#596760] hover:bg-[#f3f5f1]">Limpiar</a>
                </form>
                <p class="mt-4 text-xs leading-5 text-[#71807a]">Estos saldos son cartera inicial separada: no crean facturas de compra o venta ni afectan las existencias. Los pagos registrados aquí actualizan el saldo pendiente de cada factura.</p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#e7ece8] px-5 py-4">
                    <div>
                        <h3 class="font-black text-[#192522]">Facturas pendientes</h3>
                        <p class="mt-1 text-xs text-[#71807a]">{{ $invoices->total() }} registros de cartera</p>
                    </div>
                    <a href="{{ route('accounting.opening-balances.template') }}" class="rounded-lg border border-[#d7dfd8] px-3 py-2 text-xs font-bold text-[#227c70] hover:bg-[#f3f5f1]">Descargar plantilla CSV</a>
                </div>

                @if($invoices->isEmpty())
                    <div class="px-5 py-12 text-center">
                        <p class="font-bold text-[#192522]">No hay facturas de saldos iniciales</p>
                        <p class="mt-1 text-sm text-[#71807a]">Importa la plantilla desde el comprobante de saldos iniciales para cargar cartera.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-[#e7ece8] text-left text-sm">
                            <thead class="bg-[#f8faf8] text-[11px] uppercase tracking-wide text-[#71807a]">
                                <tr>
                                    <th class="px-4 py-3 font-bold">Tipo / Tercero</th>
                                    <th class="px-4 py-3 font-bold">Factura / CUFE</th>
                                    <th class="px-4 py-3 font-bold">Fechas</th>
                                    <th class="px-4 py-3 text-right font-bold">Saldo inicial</th>
                                    <th class="px-4 py-3 text-right font-bold">Abonado</th>
                                    <th class="px-4 py-3 text-right font-bold">Pendiente</th>
                                    <th class="px-4 py-3 text-right font-bold">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e7ece8]">
                                @foreach($invoices as $invoice)
                                    @php
                                        $paidAmount = (float) ($invoice->paid_total ?? 0);
                                        $balance = max(0, round((float) $invoice->opening_balance - $paidAmount, 2));
                                    @endphp
                                    <tr class="align-top">
                                        <td class="px-4 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $invoice->invoice_type === 'cliente' ? 'bg-[#edf7f4] text-[#227c70]' : 'bg-[#fbefea] text-[#b65338]' }}">{{ $invoice->invoice_type }}</span>
                                            <p class="mt-2 font-bold text-[#192522]">{{ $invoice->third_party_name }}</p>
                                            <p class="text-xs text-[#71807a]">{{ $invoice->third_party_document }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="font-bold text-[#192522]">{{ $invoice->invoice_number }}</p>
                                            <p class="mt-1 max-w-xs break-all text-[11px] text-[#71807a]">{{ $invoice->cufe ?: 'Sin CUFE' }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-xs text-[#596760]">
                                            <p>Factura: {{ $invoice->invoice_date->format('d/m/Y') }}</p>
                                            <p class="mt-1">Vence: {{ $invoice->due_date?->format('d/m/Y') ?? 'Sin fecha' }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right font-medium text-[#192522]">${{ number_format((float) $invoice->opening_balance, 2, ',', '.') }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right font-medium text-[#596760]">${{ number_format($paidAmount, 2, ',', '.') }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right font-black {{ $balance > 0 ? 'text-[#b65338]' : 'text-[#227c70]' }}">${{ number_format($balance, 2, ',', '.') }}</td>
                                        <td class="px-4 py-4 text-right">
                                            @if($balance > 0)
                                                <details class="inline-block text-left">
                                                    <summary class="cursor-pointer whitespace-nowrap rounded-lg bg-[#227c70] px-3 py-2 text-xs font-bold text-white hover:bg-[#1a5f55]">Registrar abono</summary>
                                                    <form method="POST" action="{{ route('accounting.opening-balances.payments.store', $invoice) }}" class="mt-2 grid min-w-56 gap-2 rounded-xl border border-[#d7dfd8] bg-white p-3 shadow-lg">
                                                        @csrf
                                                        <label class="grid gap-1 text-[11px] font-semibold text-[#596760]">Valor
                                                            <input type="number" name="amount" min="0.01" max="{{ number_format($balance, 2, '.', '') }}" step="0.01" required class="rounded-md border-gray-300 text-sm" placeholder="Máx. {{ number_format($balance, 2, ',', '.') }}">
                                                        </label>
                                                        <label class="grid gap-1 text-[11px] font-semibold text-[#596760]">Fecha de pago
                                                            <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required class="rounded-md border-gray-300 text-sm">
                                                        </label>
                                                        <label class="grid gap-1 text-[11px] font-semibold text-[#596760]">Medio de pago
                                                            <input type="text" name="method" maxlength="100" class="rounded-md border-gray-300 text-sm" placeholder="Efectivo, transferencia...">
                                                        </label>
                                                        <label class="grid gap-1 text-[11px] font-semibold text-[#596760]">Nota
                                                            <input type="text" name="notes" maxlength="255" class="rounded-md border-gray-300 text-sm" placeholder="Opcional">
                                                        </label>
                                                        <button type="submit" class="rounded-md bg-[#227c70] px-3 py-2 text-xs font-bold text-white hover:bg-[#1a5f55]">Guardar abono</button>
                                                    </form>
                                                </details>
                                            @else
                                                <span class="text-xs font-bold text-[#227c70]">Pagada</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-[#e7ece8] px-5 py-4">{{ $invoices->links() }}</div>
                @endif
            </section>
        </div>
    </main>
</x-app-layout>
