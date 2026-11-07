<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Contabilidad / {{ $voucherTypeLabel ?? 'Comprobantes' }}</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">{{ $voucherTypeLabel ?? 'Comprobantes contables' }}</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('accounting.index') }}" class="rounded-xl border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70] hover:bg-[#f3f5f1]">Contabilidad</a>
                <a href="{{ route('accounting.puc.index') }}" class="rounded-xl border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70] hover:bg-[#f3f5f1]">PUC</a>
                <a href="{{ route('reports.index', ['type' => 'all']) }}" class="rounded-xl border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70] hover:bg-[#f3f5f1]">Todos los comprobantes</a>
                <a href="{{ route('currency.index') }}" class="rounded-xl border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70] hover:bg-[#f3f5f1]">TRM / Monedas</a>
                @if($voucherType === 'saldos_iniciales')
                    <a href="{{ route('accounting.opening-balances.index') }}" class="rounded-xl border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70] hover:bg-[#f3f5f1]">Cartera importada</a>
                    <button type="button" onclick="document.getElementById('opening-balance-import-modal').classList.remove('hidden')" class="rounded-xl bg-[#227c70] px-3 py-2 text-xs font-bold text-white hover:bg-[#1a5f55]">Importar Excel</button>
                @endif
                @if(!$voucherType && !$commercialDocument)
                    <button type="button" onclick="document.getElementById('voucher-import-modal').classList.remove('hidden')" class="rounded-xl bg-[#227c70] px-3 py-2 text-xs font-bold text-white hover:bg-[#1a5f55]">Importar servicios</button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            @if($selectedVoucher)
                <div class="lg:col-span-2">
                    <x-document-preview
                        title="Comprobante contable"
                        :number="$selectedVoucher->consecutive"
                        :date="$selectedVoucher->voucher_date?->format('d/m/Y')"
                        party-label="Tercero"
                        :party="$selectedVoucher->third_party"
                        :fields="[
                            ['label' => 'Tipo', 'value' => strtoupper(str_replace('_', ' ', $selectedVoucher->voucher_type))],
                            ['label' => 'Descripción', 'value' => $selectedVoucher->description],
                            ['label' => 'Creado por', 'value' => $selectedVoucher->creator?->name],
                        ]"
                        :columns="[
                            ['key' => 'account.code', 'label' => 'Cuenta'],
                            ['key' => 'account.name', 'label' => 'Nombre'],
                            ['key' => 'detail', 'label' => 'Detalle'],
                            ['key' => 'debit', 'label' => 'Débito'],
                            ['key' => 'credit', 'label' => 'Crédito'],
                        ]"
                        :rows="$selectedVoucher->lines"
                        :totals="[['label' => 'Total débito / crédito', 'value' => '$'.number_format((float) $selectedVoucher->total_debit, 2)]]"
                        :actions="[['label' => 'Ver contabilización', 'url' => route('accounting.vouchers.accounting', $selectedVoucher)]]"
                    />
                </div>
            @endif
            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h1 class="mb-5 text-lg font-bold text-gray-800">{{ $voucherTypeLabel ? 'Nuevo '.$voucherTypeLabel : 'Nuevo comprobante' }}</h1>
                @if(session('success')) <div class="mb-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div> @endif
                @if($errors->any()) <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div> @endif
                @if($commercialDocument)
                    <div class="mb-4 rounded-md border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900">
                        <strong>Contabilización manual de {{ $commercialDocument->consecutive }}</strong>
                        <span class="block">Tercero: {{ $commercialDocument->third_party_name }} · Total de la nota: ${{ number_format($commercialDocument->total, 2) }}</span>
                        <span class="block text-xs">Selecciona las cuentas PUC. El sistema exige que débito y crédito coincidan con el total.</span>
                    </div>
                @endif

                <form method="POST" action="{{ $voucherType ? route('accounting.vouchers.store.typed', ['voucherType' => $voucherType]) : route('accounting.vouchers.store') }}" id="voucher-form">
                    @csrf
                    @if($commercialDocument)<input type="hidden" name="commercial_document_id" value="{{ $commercialDocument->id }}">@endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Tipo de comprobante</label>
                            @if($voucherType)
                                <input type="hidden" name="voucher_type" value="{{ $voucherType }}">
                                <div class="flex min-h-10 items-center rounded-md border border-[#d7dfd8] bg-[#f3f5f1] px-3 text-sm font-bold text-[#192522]">{{ $voucherTypeLabel }}</div>
                            @else
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
                            @endif
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Fecha</label>
                            <input type="date" name="voucher_date" value="{{ old('voucher_date', $voucherPrefill['voucher_date'] ?? now()->toDateString()) }}" required class="w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Consecutivo</label>
                            <input type="text" name="consecutive" value="{{ old('consecutive', $voucherPrefill['consecutive'] ?? $nextConsecutive ?? 'COM-'.now()->format('YmdHis')) }}" required class="w-full rounded-md border-gray-300 text-sm">
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
                        <button class="rounded-md bg-[#227c70] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1a5f55]">Guardar comprobante</button>
                    </div>
                </form>
            </section>

            <aside class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-bold text-gray-800">Historial de {{ $voucherTypeLabel ?? 'comprobantes' }}</h2>
                <div class="space-y-3">
                    @forelse($vouchers as $voucher)
                        <article class="rounded-md border border-gray-100 p-3">
                            <div class="flex justify-between text-xs"><strong>{{ strtoupper(str_replace('_', ' ', $voucher->voucher_type)) }}</strong><span>{{ $voucher->consecutive }}</span></div>
                            <p class="mt-1 text-xs text-gray-500">{{ $voucher->voucher_date }} · {{ $voucher->creator?->name ?? 'Sistema' }}</p>
                            <p class="mt-2 text-sm font-semibold text-[#227c70]">${{ number_format($voucher->total_debit, 2) }}</p>
                            <div class="mt-2 flex flex-wrap gap-1 text-xs">
                                @if($voucherType)
                                    <a href="{{ route('accounting.vouchers.index.typed', ['voucherType' => $voucher->voucher_type, 'preview' => $voucher->id]) }}" class="rounded border px-2 py-1">Vista previa</a>
                                @else
                                    <a href="{{ route('accounting.vouchers.index', ['preview' => $voucher->id]) }}" class="rounded border px-2 py-1">Vista previa</a>
                                @endif
                                <a href="{{ route('accounting.vouchers.statement', $voucher) }}" class="rounded border px-2 py-1">Imprimir</a>
                                <button type="button" onclick="openVoucherModal({{ $voucher->id }})" class="rounded border px-2 py-1">Asiento contable</button>
                                <a href="{{ route('accounting.vouchers.statement', $voucher) }}?download=1" class="rounded border px-2 py-1">Descargar</a>
                                <a href="{{ route('accounting.vouchers.statement', $voucher) }}" class="rounded border px-2 py-1">Estado de cuenta</a>
                                @if(in_array($voucher->voucher_type, ['egreso', 'recibo_caja'], true))
                                    <form method="POST" action="{{ route('accounting.vouchers.email', $voucher) }}" class="flex gap-1">@csrf<input type="email" name="email" required placeholder="correo" class="w-32 rounded border px-1 py-1"><button class="rounded bg-[#227c70] px-2 py-1 text-white hover:bg-[#1a5f55]">Enviar</button></form>
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
    @if($voucherType === 'saldos_iniciales')
        <div id="opening-balance-import-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-black/50 p-4" onclick="if(event.target===this) this.classList.add('hidden')">
            <section class="w-full max-w-2xl space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#227c70]">Importación masiva</p>
                        <h3 class="mt-1 text-lg font-black text-[#192522]">Saldos iniciales</h3>
                    </div>
                    <button type="button" onclick="document.getElementById('opening-balance-import-modal').classList.add('hidden')" class="text-xl text-gray-400" aria-label="Cerrar">×</button>
                </div>
                <p class="text-sm leading-6 text-gray-600">
                    Cada fila crea un movimiento contable. Para cartera, agrega los datos de tercero y factura; el valor de débito/crédito será el saldo pendiente.
                    Los XML opcionales se usan para leer CUFE y completar fechas. <strong>No se vuelven a crear compras o ventas ni se mueve inventario.</strong>
                </p>
                <a href="{{ route('accounting.opening-balances.template') }}" class="inline-flex rounded-lg border border-[#d7dfd8] px-3 py-2 text-xs font-bold text-[#227c70]">Descargar plantilla CSV para Excel</a>
                <form method="POST" action="{{ route('accounting.opening-balances.import') }}" enctype="multipart/form-data" class="grid gap-3">
                    @csrf
                    <label class="grid gap-1 text-xs font-semibold text-gray-600">Fecha del comprobante
                        <input type="date" name="voucher_date" value="{{ old('voucher_date', now()->toDateString()) }}" required class="rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="grid gap-1 text-xs font-semibold text-gray-600">Archivo Excel o CSV (máximo 500 filas)
                        <input type="file" name="file" accept=".xlsx,.csv,.txt" required class="rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="grid gap-1 text-xs font-semibold text-gray-600">XML DIAN de facturas (opcional, puede seleccionar varios)
                        <input type="file" name="xml_files[]" accept=".xml,text/xml,application/xml" multiple class="rounded-lg border-gray-300 text-sm">
                    </label>
                    <p class="text-xs leading-5 text-gray-500">Las filas de clientes se registran al débito y las de proveedores al crédito. Las demás filas deben cuadrar el comprobante. Los terceros deben existir en el módulo de terceros.</p>
                    <button type="submit" class="rounded-lg bg-[#227c70] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1a5f55]">Importar saldos y crear comprobante</button>
                </form>
            </section>
        </div>
    @endif
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
        function filterThirdParties(voucherType, resetSelection = true) {
            const supplierTypes = ['egreso', 'seguridad_social', 'provision_empleados', 'nota_debito_proveedor'];
            const profile = supplierTypes.includes(voucherType) ? 'supplier' : 'customer';
            document.querySelectorAll('#third-party-select optgroup option').forEach((option) => {
                option.hidden = option.dataset.profile !== profile;
            });
            if (resetSelection) {
                document.getElementById('third-party-select').value = '';
            }
        }
        const voucherTypeInput = document.querySelector('[name="voucher_type"]');
        voucherTypeInput.addEventListener('change', (event) => filterThirdParties(event.target.value));
        filterThirdParties(voucherTypeInput.value, false);
        updateTotals();
    </script>
    <x-voucher-modal />

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
