<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Tesorería</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Arqueo de caja</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-4xl space-y-6">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
            @endif

            <!-- Selector de caja -->
            <div class="flex gap-2">
                @foreach($fundTypes as $key => $label)
                    <a href="{{ route('cash-register.index', ['fund_type' => $key]) }}" class="rounded-lg px-4 py-2 text-sm font-bold {{ $fundType === $key ? 'bg-[#227c70] text-white' : 'border border-[#d7dfd8] bg-white text-[#227c70]' }}">{{ $label }}</a>
                @endforeach
            </div>

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
                        <div class="rounded-lg bg-[#fff8df] p-3"><p class="text-[10px] font-bold uppercase text-[#8b6811]">Efectivo esperado</p><p class="font-black text-[#8b6811]">${{ number_format($openSession->expected_cash, 2) }}</p></div>
                    </div>

                    <form method="POST" action="{{ route('cash-register.close', $openSession) }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_2fr_auto] sm:items-end">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">Efectivo contado</label>
                            <input type="number" step="0.01" min="0" name="counted_cash" required placeholder="0.00" class="w-full rounded-lg border-[#cbd6cf] text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">Notas (opcional)</label>
                            <input type="text" name="notes" placeholder="Observaciones del cuadre" class="w-full rounded-lg border-[#cbd6cf] text-sm">
                        </div>
                        <button class="rounded-lg bg-[#b65338] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#9c4630]" onclick="return confirm('¿Cerrar la caja con el efectivo contado?')">🔒 Cerrar caja</button>
                    </form>
                </section>
            @else
                <!-- No hay sesión abierta: formulario para abrir -->
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                    <h3 class="mb-1 text-sm font-black uppercase tracking-wider text-[#227c70]">Abrir {{ $fundTypes[$fundType] }}</h3>
                    <p class="mb-4 text-xs text-[#71807a]">Indica con cuánto efectivo empiezas el turno. El sistema calculará automáticamente las entradas (recibos de caja) y salidas (egresos) registradas mientras la caja esté abierta.</p>
                    <form method="POST" action="{{ route('cash-register.open') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="fund_type" value="{{ $fundType }}">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">Base inicial</label>
                            <input type="number" step="0.01" min="0" name="opening_balance" required placeholder="0.00" class="w-48 rounded-lg border-[#cbd6cf] text-sm">
                        </div>
                        <button class="rounded-lg bg-[#227c70] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1a5f55]">🔓 Abrir caja</button>
                    </form>
                </section>
            @endif

            <!-- Historial de cierres -->
            <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                <h3 class="p-5 pb-0 text-sm font-black uppercase tracking-wider text-[#227c70]">Historial de cuadres</h3>
                <table class="mt-4 w-full text-left text-sm">
                    <thead class="bg-[#192522] text-xs uppercase text-white">
                        <tr>
                            <th class="p-3">Apertura</th>
                            <th class="p-3">Cierre</th>
                            <th class="p-3 text-right">Esperado</th>
                            <th class="p-3 text-right">Contado</th>
                            <th class="p-3 text-right">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e6ede8]">
                        @forelse($recentSessions as $session)
                            <tr>
                                <td class="p-3">{{ $session->opened_at->format('d/m/Y H:i') }}</td>
                                <td class="p-3">{{ $session->closed_at?->format('d/m/Y H:i') }}</td>
                                <td class="p-3 text-right">${{ number_format($session->expected_cash, 2) }}</td>
                                <td class="p-3 text-right">${{ number_format($session->counted_cash, 2) }}</td>
                                <td class="p-3 text-right font-bold {{ abs($session->difference) < 0.01 ? 'text-[#227c70]' : 'text-red-600' }}">
                                    {{ $session->difference >= 0 ? '+' : '' }}${{ number_format($session->difference, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-5 text-center text-sm text-[#71807a]">Aún no hay cuadres de caja cerrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>
