<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-black text-[#192522]">Editar comprobante {{ $voucher->consecutive }}</h2>
            <a href="{{ route('accounting.vouchers.accounting', $voucher) }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">Cancelar</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8">
        <form method="POST" action="{{ route('accounting.vouchers.update', $voucher) }}" class="mx-auto max-w-5xl space-y-5 rounded-2xl bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            <input type="hidden" name="version" value="{{ old('version', $version) }}">
            @if($errors->any())
                <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if($lockedLines)
                <p class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">Este comprobante está vinculado a un pago, transferencia u otro documento. Por ahora solo se pueden cambiar la descripción y los detalles para conservar sus saldos y vínculos.</p>
            @else
                <p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">Si está conciliado, la cuenta bancaria y su valor deben coincidir con el extracto.</p>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-bold">Fecha
                    <input type="date" name="voucher_date" value="{{ old('voucher_date', $voucher->voucher_date->toDateString()) }}" {{ $lockedLines ? 'readonly' : '' }} required class="mt-1 w-full rounded-lg border-gray-300">
                </label>
                <label class="text-sm font-bold">Tercero
                    <input type="text" name="third_party" value="{{ old('third_party', $voucher->third_party) }}" {{ $lockedLines ? 'readonly' : '' }} class="mt-1 w-full rounded-lg border-gray-300">
                </label>
                <label class="text-sm font-bold sm:col-span-2">Descripción
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description', $voucher->description) }}</textarea>
                </label>
            </div>
            <div id="voucher-lines" class="space-y-3">
                @foreach(old('lines', $voucher->lines->toArray()) as $i => $line)
                    <div class="voucher-line grid gap-2 rounded-lg border border-gray-200 p-3 sm:grid-cols-4">
                        <label class="text-xs font-bold">Cuenta PUC
                            @if($lockedLines)
                                <input type="hidden" name="lines[{{ $i }}][chart_of_account_id]" value="{{ $line['chart_of_account_id'] }}">
                                <span class="mt-2 block">{{ $voucher->lines[$i]->account?->code }} - {{ $voucher->lines[$i]->account?->name }}</span>
                            @else
                                <select name="lines[{{ $i }}][chart_of_account_id]" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">Selecciona cuenta</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" @selected($account->id == $line['chart_of_account_id'])>{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </label>
                        <label class="text-xs font-bold">Detalle<input name="lines[{{ $i }}][detail]" value="{{ $line['detail'] ?? '' }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                        <label class="text-xs font-bold">Débito<input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] }}" {{ $lockedLines ? 'readonly' : '' }} required class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                        <label class="text-xs font-bold">Crédito<input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] }}" {{ $lockedLines ? 'readonly' : '' }} required class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                        @unless($lockedLines)
                            <button type="button" class="remove-voucher-line text-left text-xs font-bold text-red-700">Quitar cuenta</button>
                        @endunless
                    </div>
                @endforeach
            </div>
            @unless($lockedLines)
                <button type="button" id="add-voucher-line" class="rounded-lg border border-[#227c70] px-4 py-2 text-sm font-bold text-[#227c70]">＋ Agregar cuenta</button>
                <template id="voucher-line-template">
                    <div class="voucher-line grid gap-2 rounded-lg border border-gray-200 p-3 sm:grid-cols-4">
                        <label class="text-xs font-bold">Cuenta PUC
                            <select name="lines[__INDEX__][chart_of_account_id]" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Selecciona cuenta</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-bold">Detalle<input name="lines[__INDEX__][detail]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                        <label class="text-xs font-bold">Débito<input type="number" step="0.01" min="0" name="lines[__INDEX__][debit]" value="0" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                        <label class="text-xs font-bold">Crédito<input type="number" step="0.01" min="0" name="lines[__INDEX__][credit]" value="0" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                        <button type="button" class="remove-voucher-line text-left text-xs font-bold text-red-700">Quitar cuenta</button>
                    </div>
                </template>
                <script>
                    (() => {
                        const container = document.getElementById('voucher-lines');
                        const template = document.getElementById('voucher-line-template');
                        let nextIndex = Math.max(-1, ...Array.from(container.querySelectorAll('[name^="lines["]'), input => Number(input.name.match(/^lines\[(\d+)\]/)?.[1] ?? -1))) + 1;
                        document.getElementById('add-voucher-line').addEventListener('click', () => {
                            const fragment = template.content.cloneNode(true);
                            fragment.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace('__INDEX__', nextIndex); });
                            nextIndex++;
                            container.appendChild(fragment);
                        });
                        container.addEventListener('click', event => {
                            if (event.target.closest('.remove-voucher-line') && container.querySelectorAll('.voucher-line').length > 2) {
                                event.target.closest('.voucher-line').remove();
                            }
                        });
                    })();
                </script>
            @endunless
            <button type="submit" class="rounded-lg bg-[#227c70] px-4 py-2 text-sm font-bold text-white">Guardar cambios</button>
        </form>
        @if($payment)
            <form method="POST" action="{{ route('accounting.vouchers.adjust-payment', $voucher) }}" class="mx-auto mt-5 max-w-5xl space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                @csrf
                <input type="hidden" name="version" value="{{ $version }}">
                <h3 class="font-bold">Aplicar ajuste al saldo de la factura</h3>
                <p class="text-sm text-gray-600">El ajuste reduce el saldo pendiente sin cambiar el valor del banco. No lo registres si la diferencia es un abono o una retención pendiente.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-bold">Valor del ajuste
                        <input type="number" name="adjustment_total" min="0.01" step="0.01" required class="mt-1 w-full rounded-lg border-gray-300">
                    </label>
                    <label class="text-sm font-bold">Cuenta PUC de ajuste
                        <select name="adjustment_account_id" required class="mt-1 w-full rounded-lg border-gray-300">
                            <option value="">Selecciona cuenta</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <button type="submit" class="rounded-lg bg-[#227c70] px-4 py-2 text-sm font-bold text-white">Guardar ajuste</button>
            </form>
        @endif
    </div>
</x-app-layout>
