<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-800">Documento soporte</h2>
            <a href="{{ route('dashboard') }}" class="rounded border px-3 py-2 text-xs">Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-50 p-5">
        <div class="mx-auto max-w-7xl space-y-5">
            @if (session('success'))
                <div class="rounded bg-emerald-50 p-2 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded bg-red-50 p-2 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @if ($selectedDocument)
                <x-document-preview
                    title="Documento soporte"
                    :number="$selectedDocument->consecutive"
                    :date="$selectedDocument->document_date?->format('d/m/Y')"
                    party-label="Proveedor"
                    :party="$selectedDocument->supplier?->name"
                    :fields="[
                        ['label' => 'Concepto', 'value' => $selectedDocument->concept],
                        ['label' => 'Documento proveedor', 'value' => $selectedDocument->supplier?->document],
                    ]"
                    :totals="[
                        ['label' => 'Subtotal', 'value' => '$'.number_format((float) $selectedDocument->subtotal, 2)],
                        ['label' => 'IVA', 'value' => '$'.number_format((float) $selectedDocument->iva_total, 2)],
                        ['label' => 'Retención', 'value' => '$'.number_format((float) $selectedDocument->retention_total, 2)],
                        ['label' => 'Total', 'value' => '$'.number_format((float) $selectedDocument->total, 2)],
                    ]"
                    :accounting-url="$selectedDocument->accounting_voucher_id ? route('accounting.vouchers.accounting', $selectedDocument->accounting_voucher_id) : null"
                    :accounting-action="$selectedDocument->accounting_voucher_id ? null : route('support-documents.account', $selectedDocument)"
                    :accounting-message="$selectedDocument->accounting_voucher_id ? null : 'El documento aún no tiene contabilización.'"
                    :actions="array_merge(
                        [['label' => 'Abrir documento', 'url' => route('support-documents.show', $selectedDocument)]],
                        $selectedDocument->balanceDue((float) $selectedDocument->total) > 0
                            ? [['label' => 'Registrar pago · Egreso', 'url' => route('support-documents.payments.create', $selectedDocument)]]
                            : [],
                    )"
                />
            @endif

            <div class="grid gap-5 lg:grid-cols-[420px_1fr]">
                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h1 class="mb-4 font-bold">Nuevo documento soporte</h1>
                    <form method="POST" action="{{ route('support-documents.store') }}" class="space-y-3">
                        @csrf
                        @if ($nextConsecutive)
                            <input name="consecutive" value="{{ $nextConsecutive }}" readonly title="Asignado automáticamente por la resolución DIAN activa" class="w-full rounded border-gray-300 text-sm">
                        @else
                            <input name="consecutive" value="DS-{{ now()->format('YmdHis') }}" required placeholder="Consecutivo" class="w-full rounded border-gray-300 text-sm">
                        @endif
                        <input type="date" name="document_date" value="{{ now()->toDateString() }}" required class="w-full rounded border-gray-300 text-sm">
                        <select name="third_party_id" required class="w-full rounded border-gray-300 text-sm">
                            <option value="">Seleccione proveedor</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}{{ $supplier->document ? ' - '.$supplier->document : '' }}</option>
                            @endforeach
                        </select>
                        <input name="concept" required placeholder="Concepto del pago" class="w-full rounded border-gray-300 text-sm">
                        <input name="subtotal" id="subtotal" type="number" min="0" step="0.01" required placeholder="Subtotal" class="w-full rounded border-gray-300 text-sm">
                        <input name="iva_total" id="iva" type="number" min="0" step="0.01" value="0" placeholder="IVA" class="w-full rounded border-gray-300 text-sm">
                        <select name="withholding_concept" class="w-full rounded border-gray-300 text-sm">
                            @foreach ($withholdings['concepts'] as $key => $concept)
                                <option value="{{ $key }}">{{ $concept['label'] }}</option>
                            @endforeach
                        </select>
                        <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="skip_dian" value="1"> No enviar a la DIAN (uso interno)</label>
                        <button class="w-full rounded bg-pink-600 px-3 py-2 text-sm font-bold text-white">Guardar documento</button>
                    </form>
                </section>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="mb-4 font-bold">Documentos recientes</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-gray-50 text-xs uppercase">
                                <tr><th class="p-2">Consecutivo</th><th class="p-2">Proveedor</th><th class="p-2">Concepto</th><th class="p-2">Total</th><th class="p-2">Acciones</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($documents as $document)
                                    <tr class="border-b">
                                        <td class="p-2">{{ $document->consecutive }}</td>
                                        <td class="p-2">{{ $document->supplier->name }}</td>
                                        <td class="p-2">{{ $document->concept }}</td>
                                        <td class="p-2">${{ number_format($document->total, 2) }}</td>
                                        <td class="space-x-2 p-2">
                                            <a href="{{ route('support-documents.index', ['preview' => $document->id]) }}" class="text-pink-600">Vista previa</a>
                                            <a href="{{ route('support-documents.xml', $document) }}" class="text-pink-600">XML</a>
                                            <a href="{{ route('support-documents.show', $document) }}" target="_blank" rel="noopener" class="text-pink-600">Ver documento</a>
                                            <a href="{{ route('support-documents.edit', $document) }}" class="text-pink-600">✏ Editar</a>
                                            <a href="{{ route('support-documents.statement', $document) }}" target="_blank" rel="noopener" class="text-pink-600">Cartera</a>
                                            @if ($document->accounting_voucher_id)
                                                <a href="{{ route('accounting.vouchers.accounting', $document->accounting_voucher_id) }}" target="_blank" rel="noopener" class="text-pink-600">Ver contabilización</a>
                                            @else
                                                <form method="POST" action="{{ route('support-documents.account', $document) }}" class="inline-block" onsubmit="return confirm('¿Contabilizar este documento ahora?')">
                                                    @csrf
                                                    <button type="submit" class="text-pink-600">Contabilizar ahora</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('support-documents.email', $document) }}" class="inline-flex gap-1">
                                                @csrf
                                                <input name="email" type="email" value="{{ $document->supplier->email }}" required placeholder="Correo" class="w-32 rounded border text-xs">
                                                <button class="rounded bg-pink-600 px-2 py-1 text-xs text-white">Enviar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="p-3">No hay documentos soporte.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <x-voucher-modal />
</x-app-layout>
