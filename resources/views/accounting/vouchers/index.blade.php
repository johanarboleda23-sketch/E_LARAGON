<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-pink-500">Contabilidad</p>
                <h2 class="text-xl font-bold text-gray-800">Comprobantes contables</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-200 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50">Tablero</a>
            <a href="{{ route('accounting.puc.index') }}" class="rounded-md border border-pink-200 px-3 py-2 text-xs text-pink-700 hover:bg-pink-50">PUC</a>
            <a href="{{ route('reports.index', ['type' => 'all']) }}" class="rounded-md border border-pink-200 px-3 py-2 text-xs text-pink-700 hover:bg-pink-50">Todos los comprobantes</a>
            <a href="{{ route('currency.index') }}" class="rounded-md border border-pink-200 px-3 py-2 text-xs text-pink-700 hover:bg-pink-50">TRM / Monedas</a>
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
                                <option value="ajuste_contable" @selected(old('voucher_type', request('type')) === 'ajuste_contable')>Ajuste contable</option>
                                <option value="nomina" @selected(old('voucher_type', request('type')) === 'nomina')>Nómina</option>
                                <option value="seguridad_social" @selected(old('voucher_type', request('type')) === 'seguridad_social')>Pago de seguridad social</option>
                                <option value="provision_empleados" @selected(old('voucher_type', request('type')) === 'provision_empleados')>Provisión de empleados</option>
                                <option value="nota_credito_cliente" @selected(old('voucher_type', $voucherPrefill['voucher_type'] ?? request('type')) === 'nota_credito_cliente')>Nota crédito cliente</option>
                                <option value="nota_credito_proveedor" @selected(old('voucher_type', request('type')) === 'nota_credito_proveedor')>Nota crédito proveedor</option>
                                <option value="nota_debito_cliente" @selected(old('voucher_type', request('type')) === 'nota_debito_cliente')>Nota débito cliente</option>
                                <option value="nota_debito_proveedor" @selected(old('voucher_type', $voucherPrefill['voucher_type'] ?? request('type')) === 'nota_debito_proveedor')>Nota débito proveedor</option>
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
                        <datalist id="chart-account-options">
                            @foreach($accounts as $account)
                                <option value="{{ $account->code }} - {{ $account->name }}"></option>
                            @endforeach
                        </datalist>
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500">
                                <tr><th class="p-2">Cuenta PUC</th><th class="p-2">Detalle</th><th class="p-2">Débito</th><th class="p-2">Crédito</th><th></th></tr>
                            </thead>
                            <tbody id="voucher-lines">
                                <tr class="voucher-line border-b">
                                    <td class="p-2"><input type="text" list="chart-account-options" aria-label="Cuenta PUC, escribe código o nombre" placeholder="Escribe código o nombre" required class="account-search w-56 rounded border-gray-300 text-xs"><input type="hidden" name="lines[0][chart_of_account_id]" class="account-id"></td>
                                    <td class="p-2"><input name="lines[0][detail]" class="w-36 rounded border-gray-300 text-xs"></td>
                                    <td class="p-2"><input type="number" step="0.01" min="0" name="lines[0][debit]" value="{{ old('lines.0.debit', $commercialDocument?->total) }}" class="debit w-28 rounded border-gray-300 text-xs"></td>
                                    <td class="p-2"><input type="number" step="0.01" min="0" name="lines[0][credit]" value="{{ old('lines.0.credit') }}" class="credit w-28 rounded border-gray-300 text-xs"></td>
                                    <td class="p-2"><button type="button" onclick="removeLine(this)" class="text-red-500">×</button></td>
                                </tr>
                                @if($commercialDocument)
                                    <tr class="voucher-line border-b">
                                        <td class="p-2"><input type="text" list="chart-account-options" aria-label="Cuenta PUC, escribe código o nombre" placeholder="Escribe código o nombre" required class="account-search w-56 rounded border-gray-300 text-xs"><input type="hidden" name="lines[1][chart_of_account_id]" class="account-id"></td>
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
                                <button type="button" onclick="openVoucherModal({{ $voucher->id }})" class="rounded border px-2 py-1">Asiento contable</button>
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
        function syncAccountSelection(input) {
            const account = accountOptions.find((option) => option.label === input.value);
            input.closest('.voucher-line').querySelector('.account-id').value = account?.id ?? '';
            input.setCustomValidity(input.value && !account ? 'Selecciona una cuenta de la lista del PUC.' : '');
        }
        document.addEventListener('input', (event) => {
            if (event.target.matches('.account-search')) syncAccountSelection(event.target);
        });
        document.addEventListener('change', (event) => {
            if (event.target.matches('.account-search')) syncAccountSelection(event.target);
        });
        function addLine() {
            const row = document.createElement('tr');
            row.className = 'voucher-line border-b';
            row.innerHTML = `<td class="p-2"><input type="text" list="chart-account-options" aria-label="Cuenta PUC, escribe código o nombre" placeholder="Escribe código o nombre" required class="account-search w-56 rounded border-gray-300 text-xs"><input type="hidden" name="lines[${lineIndex}][chart_of_account_id]" class="account-id"></td><td class="p-2"><input name="lines[${lineIndex}][detail]" class="w-36 rounded border-gray-300 text-xs"></td><td class="p-2"><input type="number" step="0.01" min="0" name="lines[${lineIndex}][debit]" class="debit w-28 rounded border-gray-300 text-xs"></td><td class="p-2"><input type="number" step="0.01" min="0" name="lines[${lineIndex}][credit]" class="credit w-28 rounded border-gray-300 text-xs"></td><td class="p-2"><button type="button" onclick="removeLine(this)" class="text-red-500">×</button></td>`;
            document.getElementById('voucher-lines').appendChild(row);
            lineIndex++;
        }
        function removeLine(button) { if (document.querySelectorAll('.voucher-line').length > 1) button.closest('tr').remove(); updateTotals(); }
        function fmt(n) { return (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function updateTotals() { document.getElementById('total-debit').textContent = '$' + fmt([...document.querySelectorAll('.debit')].reduce((sum, input) => sum + Number(input.value || 0), 0)); document.getElementById('total-credit').textContent = '$' + fmt([...document.querySelectorAll('.credit')].reduce((sum, input) => sum + Number(input.value || 0), 0)); }
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
    <x-voucher-modal />

    {{-- Botones flotantes de acceso rápido a los comprobantes más usados --}}
    <div class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3">
        <div id="voucher-fab-menu" class="hidden flex-col items-end gap-2">
            <a href="{{ route('accounting.vouchers.index.typed', 'egreso') }}" class="flex items-center gap-2 rounded-full bg-[#b65338] px-4 py-2.5 text-sm font-bold text-white shadow-lg">💸 Egreso</a>
            <a href="{{ route('accounting.vouchers.index.typed', 'recibo_caja') }}" class="flex items-center gap-2 rounded-full bg-[#227c70] px-4 py-2.5 text-sm font-bold text-white shadow-lg">🧾 Recibo de caja</a>
            <a href="{{ route('accounting.vouchers.index') }}" class="flex items-center gap-2 rounded-full bg-[#192522] px-4 py-2.5 text-sm font-bold text-white shadow-lg">📑 Comprobante contable</a>
            <div class="rounded-xl bg-white p-2 shadow-lg">
                <label class="block px-2 pb-1 text-[10px] font-bold uppercase tracking-wide text-gray-400">Otro tipo de comprobante</label>
                <select onchange="if(this.value) window.location = '{{ route('accounting.vouchers.index') }}'.concat('?type=', this.value)" class="w-48 rounded-md border-gray-300 text-xs">
                    <option value="">Seleccionar…</option>
                    @foreach(\App\Http\Controllers\AccountingVoucherController::VOUCHER_TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" onclick="document.getElementById('voucher-import-modal').classList.remove('hidden')" class="flex items-center gap-2 rounded-full bg-amber-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg">⇧ Migrar causación de servicios</button>
        </div>
        <button type="button" onclick="document.getElementById('voucher-fab-menu').classList.toggle('hidden');document.getElementById('voucher-fab-menu').classList.toggle('flex')" class="flex h-14 w-14 items-center justify-center rounded-full bg-pink-600 text-2xl font-bold text-white shadow-xl hover:bg-pink-700">+</button>
    </div>

    <div id="voucher-import-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-start justify-between">
                <h3 class="text-lg font-bold text-gray-800">Migrar causación de servicios</h3>
                <button type="button" onclick="document.getElementById('voucher-import-modal').classList.add('hidden')" class="text-gray-400">✕</button>
            </div>
            <p class="mb-3 text-xs text-gray-500">Sube hasta 500 servicios a la vez con nuestra plantilla: por cada fila se genera un comprobante con partida doble perfecta (gasto + IVA descontable como débito, cuenta por pagar e impuestos a cargo como crédito).</p>
            <a href="{{ route('accounting.vouchers.import-template') }}" class="mb-3 inline-block rounded-md border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700">⬇ Descargar plantilla</a>
            <form method="POST" action="{{ route('accounting.vouchers.import') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="file" accept=".xlsx,.csv,.txt" required class="w-full rounded border-gray-300 text-sm">
                <button class="w-full rounded bg-amber-600 px-3 py-2 text-sm font-bold text-white">Migrar servicios</button>
            </form>
        </div>
    </div>
</x-app-layout>
