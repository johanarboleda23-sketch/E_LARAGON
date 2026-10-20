<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Tesorería</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Arqueo de Caja</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                <form method="GET" action="{{ route('cash-register.index') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="fund_type" class="mb-1 block text-xs font-bold uppercase text-[#71807a]">Tipo de caja</label>
                        <select id="fund_type" name="fund_type" class="rounded-lg border-[#cbd6cf] text-sm font-bold text-[#192522] focus:ring-[#227c70]">
                            @foreach ($fundTypes as $key => $label)
                                <option value="{{ $key }}" @selected($fundType === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-[#192522] px-4 py-2 text-xs font-bold text-white hover:bg-black">
                        Consultar caja
                    </button>
                </form>
            </section>

            @if ($openSession)
                <section class="space-y-5 rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">Sesión abierta</h3>
                            <p class="mt-1 text-xs text-[#71807a]">Apertura: {{ $openSession->opened_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Abierta</span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-[#cbd6cf] bg-[#f8faf8] p-4">
                            <p class="text-[10px] font-bold uppercase text-[#71807a]">Base inicial</p>
                            <p class="mt-1 text-lg font-black text-[#192522]">${{ number_format((float) $openSession->opening_balance, 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-[#cbd6cf] bg-[#f8faf8] p-4">
                            <p class="text-[10px] font-bold uppercase text-[#71807a]">Entradas</p>
                            <p class="mt-1 text-lg font-black text-[#227c70]">${{ number_format((float) $openSession->cash_in, 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-[#cbd6cf] bg-[#f8faf8] p-4">
                            <p class="text-[10px] font-bold uppercase text-[#71807a]">Salidas</p>
                            <p class="mt-1 text-lg font-black text-[#b65338]">${{ number_format((float) $openSession->cash_out, 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-[#e2c044] bg-[#fff8df] p-4">
                            <p class="text-[10px] font-bold uppercase text-[#8b6811]">Efectivo esperado</p>
                            <p class="mt-1 text-lg font-black text-[#8b6811]">${{ number_format((float) $openSession->expected_cash, 2) }}</p>
                        </div>
                    </div>

                    <form action="{{ route('cash-register.close', $openSession) }}" method="POST" class="space-y-4 rounded-xl bg-[#192522] p-5 text-white">
                        @csrf
                        <h4 class="text-xs font-bold uppercase tracking-wider">Cerrar y realizar arqueo</h4>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="counted_cash" class="mb-1 block text-xs font-bold">Efectivo contado</label>
                                <input id="counted_cash" name="counted_cash" type="number" min="0" step="0.01" value="{{ old('counted_cash') }}" required class="w-full rounded-lg border-none text-sm font-bold text-[#192522]">
                            </div>
                            <div>
                                <label for="notes" class="mb-1 block text-xs font-bold">Observaciones</label>
                                <input id="notes" name="notes" type="text" maxlength="500" value="{{ old('notes') }}" class="w-full rounded-lg border-none text-sm text-[#192522]">
                            </div>
                        </div>
                        <button type="submit" class="rounded-lg bg-[#227c70] px-5 py-2.5 text-xs font-bold uppercase hover:bg-[#1a5f55]">
                            Cerrar caja
                        </button>
                    </form>
                </section>
            @else
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">Abrir {{ $fundTypes[$fundType] }}</h3>
                    <p class="mt-1 text-xs text-[#71807a]">Registra el saldo inicial para comenzar una nueva sesión de caja.</p>
                    <form action="{{ route('cash-register.open') }}" method="POST" class="mt-5 flex flex-wrap items-end gap-4">
                        @csrf
                        <input type="hidden" name="fund_type" value="{{ $fundType }}">
                        <div>
                            <label for="opening_balance" class="mb-1 block text-xs font-bold uppercase text-[#71807a]">Base inicial</label>
                            <input id="opening_balance" name="opening_balance" type="number" min="0" step="0.01" value="{{ old('opening_balance', 0) }}" required class="rounded-lg border-[#cbd6cf] text-sm font-bold text-[#192522]">
                        </div>
                        <button type="submit" class="rounded-lg bg-[#227c70] px-5 py-2.5 text-xs font-bold uppercase text-white hover:bg-[#1a5f55]">
                            Abrir caja
                        </button>
                    </form>
                </section>
            @endif

            <section class="space-y-4 rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-wider text-[#227c70]">Sesiones cerradas recientemente</h3>
                <div class="overflow-x-auto rounded-xl border border-[#e6ede8]">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#192522] uppercase text-white">
                            <tr>
                                <th class="p-3">Cierre</th>
                                <th class="p-3 text-right">Base</th>
                                <th class="p-3 text-right">Esperado</th>
                                <th class="p-3 text-right">Contado</th>
                                <th class="p-3 text-right">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e6ede8]">
                            @forelse ($recentSessions as $session)
                                <tr>
                                    <td class="p-3">{{ $session->closed_at?->format('d/m/Y H:i') }}</td>
                                    <td class="p-3 text-right">${{ number_format((float) $session->opening_balance, 2) }}</td>
                                    <td class="p-3 text-right">${{ number_format((float) $session->expected_cash, 2) }}</td>
                                    <td class="p-3 text-right">${{ number_format((float) $session->counted_cash, 2) }}</td>
                                    <td class="p-3 text-right">${{ number_format((float) $session->difference, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-4 text-center text-[#71807a]">No hay cierres recientes para esta caja.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
