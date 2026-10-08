<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Tesorería</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">TRM y conversor de monedas</h2>
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

            <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
                <!-- Registrar tasa -->
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                    <h3 class="mb-1 text-sm font-black uppercase tracking-wider text-[#227c70]">Registrar tasa (TRM)</h3>
                    <p class="mb-4 text-xs text-[#71807a]">Indica cuántos pesos colombianos (COP) equivalen a 1 unidad de la moneda. Puedes actualizarla cada día.</p>
                    <form method="POST" action="{{ route('currency.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">Moneda</label>
                            <input list="suggested-currencies" name="currency_code" required maxlength="10" placeholder="USD" class="w-full rounded-lg border-[#cbd6cf] text-sm uppercase">
                            <datalist id="suggested-currencies">
                                @foreach($suggestedCurrencies as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">Nombre (opcional)</label>
                            <input name="currency_name" maxlength="255" placeholder="Dólar estadounidense" class="w-full rounded-lg border-[#cbd6cf] text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">Tasa en COP</label>
                            <input name="rate_to_cop" type="number" step="0.0001" min="0.0001" required placeholder="4150.00" class="w-full rounded-lg border-[#cbd6cf] text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">Fecha</label>
                            <input name="rate_date" type="date" value="{{ now()->toDateString() }}" required class="w-full rounded-lg border-[#cbd6cf] text-sm">
                        </div>
                        <button class="w-full rounded-lg bg-[#227c70] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1a5f55]">Guardar tasa</button>
                    </form>
                </section>

                <!-- Conversor -->
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm">
                    <h3 class="mb-1 text-sm font-black uppercase tracking-wider text-[#227c70]">Conversor de monedas</h3>
                    <p class="mb-4 text-xs text-[#71807a]">Usa la última tasa registrada de cada moneda. El Peso colombiano (COP) siempre vale 1.</p>

                    <div class="grid gap-3 sm:grid-cols-[1fr_auto_1fr]">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">De</label>
                            <select id="currency-from" class="w-full rounded-lg border-[#cbd6cf] text-sm">
                                <option value="COP">COP - Peso colombiano</option>
                                @foreach($rates as $code => $rate)
                                    <option value="{{ $code }}">{{ $code }} - {{ $rate->currency_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end justify-center pb-2 text-lg">→</div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#71807a]">A</label>
                            <select id="currency-to" class="w-full rounded-lg border-[#cbd6cf] text-sm">
                                <option value="COP">COP - Peso colombiano</option>
                                @foreach($rates as $code => $rate)
                                    <option value="{{ $code }}" @selected($loop->first)>{{ $code }} - {{ $rate->currency_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-bold text-[#71807a]">Monto</label>
                        <input id="currency-amount" type="number" step="0.01" min="0" value="1" class="w-full rounded-lg border-[#cbd6cf] text-sm">
                    </div>

                    <div class="mt-5 rounded-xl bg-[#f3f5f1] p-4 text-center">
                        <p class="text-xs font-bold uppercase tracking-wider text-[#71807a]">Resultado</p>
                        <p id="currency-result" class="mt-1 text-2xl font-black text-[#192522]">0.00</p>
                    </div>
                    <p id="currency-warning" class="mt-2 hidden text-xs font-bold text-red-600">No hay una tasa registrada para esa moneda todavía.</p>
                </section>
            </div>

            <!-- Historial -->
            <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                <h3 class="p-5 pb-0 text-sm font-black uppercase tracking-wider text-[#227c70]">Historial de tasas</h3>
                <table class="mt-4 w-full text-left text-sm">
                    <thead class="bg-[#192522] text-xs uppercase text-white">
                        <tr>
                            <th class="p-3">Moneda</th>
                            <th class="p-3">Fecha</th>
                            <th class="p-3 text-right">Tasa en COP</th>
                            <th class="p-3">Registrada por</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e6ede8]">
                        @forelse($history as $rate)
                            <tr>
                                <td class="p-3 font-bold text-[#192522]">{{ $rate->currency_code }} <span class="font-normal text-[#71807a]">- {{ $rate->currency_name }}</span></td>
                                <td class="p-3">{{ $rate->rate_date->format('d/m/Y') }}</td>
                                <td class="p-3 text-right font-semibold text-[#227c70]">${{ number_format($rate->rate_to_cop, 2) }}</td>
                                <td class="p-3 text-[#71807a]">{{ $rate->creator?->name ?? 'Sistema' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-5 text-center text-sm text-[#71807a]">Aún no has registrado ninguna tasa de cambio.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>

    <script>
        const rates = {{ \Illuminate\Support\Js::from($rates->map(fn ($rate) => (float) $rate->rate_to_cop)) }};
        rates.COP = 1;

        const fromSelect = document.getElementById('currency-from');
        const toSelect = document.getElementById('currency-to');
        const amountInput = document.getElementById('currency-amount');
        const resultEl = document.getElementById('currency-result');
        const warningEl = document.getElementById('currency-warning');

        function formatNumber(value) {
            return value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        }

        function convert() {
            const from = fromSelect.value;
            const to = toSelect.value;
            const amount = Number(amountInput.value || 0);
            const fromRate = rates[from];
            const toRate = rates[to];

            if (!fromRate || !toRate) {
                warningEl.classList.remove('hidden');
                resultEl.textContent = '—';
                return;
            }

            warningEl.classList.add('hidden');
            const inCop = amount * fromRate;
            const converted = inCop / toRate;
            resultEl.textContent = formatNumber(converted) + ' ' + to;
        }

        [fromSelect, toSelect, amountInput].forEach((el) => el.addEventListener('input', convert));
        convert();
    </script>
</x-app-layout>
