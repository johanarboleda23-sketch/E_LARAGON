<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-pink-500">Contabilidad</p>
                <h2 class="text-xl font-bold text-gray-800">Comprobantes contables</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-200 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50">Tablero</a>
            <a href="{{ route('accounting.puc.index') }}" class="rounded-md border border-pink-200 px-3 py-2 text-xs text-pink-700 hover:bg-pink-50">PUC</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-50 px-4 py-8 sm:px-8">
        <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h1 class="mb-5 text-lg font-bold text-gray-800">Nuevo comprobante</h1>
                @if(session('success')) <div class="mb-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div> @endif
                @if($errors->any()) <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div> @endif
                @if($commercialDocument)
                    <div class="mb-4 rounded-md border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900">
                        <strong>Contabilización manual de {{ $commercialDocument->consecutive }}</strong>
                        <span class="block">Tercero: {{ $commercialDocument->third_party_name }} · Total de la nota: ${{ number_format($commercialDocument->total, 2) }}</span>
                        <span class="block text-xs">Selecciona las cuentas PUC. El sistema exige que débito y crédito coincidan con el total.</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('accounting.vouchers.store') }}" id="voucher-form">
                    @csrf
                    @if($commercialDocument)<input type="hidden" name="commercial_document_id" value="{{ $commercialDocument->id }}">@endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Tipo de comprobante</label>
                            <select name="voucher_type" required class="w-full rounded-md border-gray-300 text-sm">
                                <option value="egreso" @selected(old('voucher_type', $voucherPrefill['voucher_type'] ?? request('type')) === 'egreso')>Egreso / Gasto</option>
                                <option value="recibo_caja" @selected(old('voucher_type', $voucherPrefill['voucher_type'] ?? request('type')) === 'recibo_caja')>Recibo de caja</option>
                                <option value="ajuste_contable" @selected(old('voucher_type') === 'ajuste_contable')>Ajuste contable</option>
                                <option value="nomina" @selected(old('voucher_type') === 'nomina')>Nómina</option>
                                <option value="seguridad_social" @selected(old('voucher_type') === 'seguridad_social')>Pago de seguridad social</option>
                                <option value="provision_empleados" @selected(old('voucher_type') === 'provision_empleados')>Provisión de empleados</option>
                                <option value="nota_credito_cliente" @selected(old('voucher_type', $voucherPrefill['voucher_type'] ?? null) === 'nota_credito_cliente')>Nota crédito cliente</option>
                                <option value="nota_debito_proveedor" @selected(old('voucher_type', $voucherPrefill['voucher_type'] ?? null) === 'nota_debito_proveedor')>Nota débito proveedor</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Fecha</label>
                            <input type="date" name="voucher_date" value="{{ old('voucher_date', $voucherPrefill['voucher_date'] ?? now()->toDateString()) }}" required class="w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Consecutivo</label>
                            <input type="text" name="consecutive" value="{{ old('consecutive', $voucherPrefill['consecutive'] ?? 'COM-'.now()->format('YmdHis')) }}" required class="w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Tercero</label>
                            <select name="third_party" id="third-party-select" class="w-full rounded-md border-gray-300 text-sm"><option value="">Seleccione tercero</option><optgroup label="Proveedores">@foreach($suppliers as $supplier)<option data-profile="supplier" value="{{ $supplier->name }}" @selected(old('third_party', $voucherPrefill['third_party'] ?? null) === $supplier->name)>{{ $supplier->name }}</option>@endforeach</optgroup><optgroup label="Clientes">@foreach($customers as $customer)<option data-profile="customer" value="{{ $customer->name }}" @selected(old('third_party', $voucherPrefill['third_party'] ?? null) === $customer->name)>{{ $customer->name }}</option>@endforeach</optgroup></select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Descripción</label>
                            <textarea name="description" rows="2" class="w-full rounded-md border-gray-300 text-sm">{{ old('description', $voucherPrefill['description'] ?? '') }}</textarea>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500">
                                <tr><th class="p-2">Cuenta PUC</th><th class="p-2">Detalle</th><th class="p-2">Débito</th><th class="p-2">Crédito</th><th></th></tr>
                            </thead>
                            <tbody id="voucher-lines">
                                <tr class="voucher-line border-b">
                                    <td class="p-2"><select name="lines[0][chart_of_account_id]" required class="w-56 rounded border-gray-300 text-xs"><option value="">Seleccione cuenta</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></td>
                                    <td class="p-2"><input name="lines[0][detail]" class="w-36 rounded border-gray-300 text-xs"></td>
                                    <td class="p-2"><input type="number" step="0.01" min="0" name="lines[0][debit]" value="{{ old('lines.0.debit', $commercialDocument?->total) }}" class="debit w-28 rounded border-gray-300 text-xs"></td>
                                    <td class="p-2"><input type="number" step="0.01" min="0" name="lines[0][credit]" value="{{ old('lines.0.credit') }}" class="credit w-28 rounded border-gray-300 text-xs"></td>
                                    <td class="p-2"><button type="button" onclick="removeLine(this)" class="text-red-500">×</button></td>
                                </tr>
                                @if($commercialDocument)
                                    <tr class="voucher-line border-b">
                                        <td class="p-2"><select name="lines[1][chart_of_account_id]" required class="w-56 rounded border-gray-300 text-xs"><option value="">Seleccione cuenta</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></td>
                                        <td class="p-2"><input name="lines[1][detail]" value="Contrapartida {{ $commercialDocument->consecutive }}" class="w-36 rounded border-gray-300 text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.01" min="0" name="lines[1][debit]" value="{{ old('lines.1.debit') }}" class="debit w-28 rounded border-gray-300 text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.01" min="0" name="lines[1][credit]" value="{{ old('lines.1.credit', $commercialDocument->total) }}" class="credit w-28 rounded border-gray-300 text-xs"></td>
                                        <td class="p-2"><button type="button" onclick="removeLine(this)" class="text-red-500">×</button></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <button type="button" onclick="addLine()" class="mt-3 rounded-md bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700">+ Agregar cuenta</button>
                    <div class="mt-5 flex items-center justify-between border-t pt-4 text-sm font-bold">
                        <span>Débito: <strong id="total-debit">$0.00</strong> | Crédito: <strong id="total-credit">$0.00</strong></span>
                        <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Guardar comprobante</button>
                    </div>
                </form>
            </section>

            <aside class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-bold text-gray-800">Últimos comprobantes</h2>
                <div class="space-y-3">
                    @forelse($vouchers as $voucher)
                        <article class="rounded-md border border-gray-100 p-3">
                            <div class="flex justify-between text-xs"><strong>{{ strtoupper(str_replace('_', ' ', $voucher->voucher_type)) }}</strong><span>{{ $voucher->consecutive }}</span></div>
                            <p class="mt-1 text-xs text-gray-500">{{ $voucher->voucher_date }} · {{ $voucher->creator?->name ?? 'Sistema' }}</p>
                            <p class="mt-2 text-sm font-semibold text-pink-700">${{ number_format($voucher->total_debit, 2) }}</p>
                            <div class="mt-2 flex flex-wrap gap-1 text-xs">
                                <a href="{{ route('accounting.vouchers.statement', $voucher) }}" class="rounded border px-2 py-1">Imprimir</a>
                                <a href="{{ route('accounting.vouchers.accounting', $voucher) }}" class="rounded border px-2 py-1">Contabilización</a>
                                <a href="{{ route('accounting.vouchers.statement', $voucher) }}?download=1" class="rounded border px-2 py-1">Descargar</a>
                                <a href="{{ route('accounting.vouchers.statement', $voucher) }}" class="rounded border px-2 py-1">Estado de cuenta</a>
                                @if(in_array($voucher->voucher_type, ['egreso', 'recibo_caja'], true))
                                    <form method="POST" action="{{ route('accounting.vouchers.email', $voucher) }}" class="flex gap-1">@csrf<input type="email" name="email" required placeholder="correo" class="w-32 rounded border px-1 py-1"><button class="rounded bg-pink-600 px-2 py-1 text-white">Enviar</button></form>
                                @endif
                                <form method="POST" action="{{ route('accounting.vouchers.destroy', $voucher) }}" onsubmit="return confirm('¿Eliminar comprobante?')">@csrf @method('DELETE')<button class="rounded border border-red-200 px-2 py-1 text-red-600">Eliminar</button></form>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-gray-500">No hay comprobantes registrados.</p>
                    @endforelse
                </div>
            </aside>
        </div>
    </div>

    <script>
        let lineIndex = {{ $commercialDocument ? 2 : 1 }};
        const accountOptions = @json($accounts->map(fn ($account) => ['id' => $account->id, 'label' => $account->code . ' - ' . $account->name]));
        function addLine() {
            const row = document.createElement('tr');
            row.className = 'voucher-line border-b';
            row.innerHTML = `<td class="p-2"><select name="lines[${lineIndex}][chart_of_account_id]" required class="w-56 rounded border-gray-300 text-xs"><option value="">Seleccione cuenta</option>${accountOptions.map((account) => `<option value="${account.id}">${account.label}</option>`).join('')}</select></td><td class="p-2"><input name="lines[${lineIndex}][detail]" class="w-36 rounded border-gray-300 text-xs"></td><td class="p-2"><input type="number" step="0.01" min="0" name="lines[${lineIndex}][debit]" class="debit w-28 rounded border-gray-300 text-xs"></td><td class="p-2"><input type="number" step="0.01" min="0" name="lines[${lineIndex}][credit]" class="credit w-28 rounded border-gray-300 text-xs"></td><td class="p-2"><button type="button" onclick="removeLine(this)" class="text-red-500">×</button></td>`;
            document.getElementById('voucher-lines').appendChild(row);
            lineIndex++;
        }
        function removeLine(button) { if (document.querySelectorAll('.voucher-line').length > 1) button.closest('tr').remove(); updateTotals(); }
        function updateTotals() { document.getElementById('total-debit').textContent = '$' + [...document.querySelectorAll('.debit')].reduce((sum, input) => sum + Number(input.value || 0), 0).toFixed(2); document.getElementById('total-credit').textContent = '$' + [...document.querySelectorAll('.credit')].reduce((sum, input) => sum + Number(input.value || 0), 0).toFixed(2); }
        document.addEventListener('input', updateTotals);
        document.querySelector('[name="voucher_type"]').addEventListener('change', (event) => {
            const supplierTypes = ['egreso', 'seguridad_social', 'provision_empleados', 'nota_debito_proveedor'];
            const profile = supplierTypes.includes(event.target.value) ? 'supplier' : 'customer';
            document.querySelectorAll('#third-party-select optgroup option').forEach((option) => {
                option.hidden = option.dataset.profile !== profile;
            });
            document.getElementById('third-party-select').value = '';
        });
        updateTotals();
    </script>
</x-app-layout>
