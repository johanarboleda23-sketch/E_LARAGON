<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-teal-700">Gestión comercial</p>
                <h2 class="text-xl font-bold text-gray-800">{{ $configuration['label'] }}</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700">Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <nav aria-label="Módulos comerciales" class="mb-5 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                @foreach ([
                    'quotation' => ['label' => 'Cotizaciones', 'route' => 'commercial-documents.quotations'],
                    'sales_order' => ['label' => 'Órdenes de venta', 'route' => 'commercial-documents.sales-orders'],
                    'remission' => ['label' => 'Remisiones', 'route' => 'commercial-documents.remissions'],
                    'purchase_order' => ['label' => 'Órdenes de compra', 'route' => 'commercial-documents.purchase-orders'],
                    'customer_credit_note' => ['label' => 'Nota crédito clientes', 'route' => 'commercial-documents.customer-credit-notes'],
                    'supplier_debit_note' => ['label' => 'Nota débito proveedores', 'route' => 'commercial-documents.supplier-debit-notes'],
                ] as $moduleType => $module)
                    <a href="{{ route($module['route']) }}" @class([
                        'rounded-md px-3 py-2 text-sm font-semibold',
                        'bg-teal-700 text-white' => $type === $moduleType,
                        'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100' => $type !== $moduleType,
                    ])>{{ $module['label'] }}</a>
                @endforeach
            </nav>

            @if (session('success'))
                <div role="status" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div role="alert" class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section aria-labelledby="document-form-title" class="border-b border-gray-200 pb-7">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 id="document-form-title" class="text-lg font-bold text-gray-900">
                            {{ $currentDocument ? 'Editar borrador '.$currentDocument->consecutive : 'Nuevo '.$configuration['label'] }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            @if ($isNote)
                                La nota debe referenciar una factura del mismo tercero. El envío a la DIAN requiere integración configurada.
                            @else
                                Guardar conserva el documento como borrador; la conversión actualiza inventario.
                            @endif
                        </p>
                    </div>
                    @if ($currentDocument)
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ in_array($currentDocument->status, ['converted', 'accounted'], true) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $currentDocument->status === 'converted' ? 'Convertido' : ($currentDocument->status === 'accounted' ? 'Contabilizada' : 'Borrador') }}
                        </span>
                    @endif
                </div>

                <form id="commercial-document-form" method="POST" action="{{ route($configuration['storeRoute']) }}" class="space-y-5">
                    @csrf
                    @if ($currentDocument)
                        <input type="hidden" name="document_id" value="{{ $currentDocument->id }}">
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <label class="text-sm font-medium text-gray-700">
                            Consecutivo
                            <input name="consecutive" value="{{ old('consecutive', $currentDocument?->consecutive ?? $defaultConsecutive) }}" @readonly($type === 'sales_order' && $currentDocument) required maxlength="60" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            {{ $configuration['partyLabel'] }}
                            <select id="third-party" name="third_party_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                <option value="">Selecciona {{ mb_strtolower($configuration['partyLabel']) }}</option>
                                @foreach ($thirdParties as $thirdParty)
                                    <option value="{{ $thirdParty->id }}" data-email="{{ $thirdParty->email }}" data-document="{{ $thirdParty->document }}" data-name="{{ $thirdParty->name }}" @selected((string) old('third_party_id', $currentDocument?->third_party_id) === (string) $thirdParty->id)>
                                        {{ $thirdParty->name }}{{ $thirdParty->document ? ' · '.$thirdParty->document : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Correo de envío
                            <input id="recipient-email" name="recipient_email" type="email" value="{{ old('recipient_email', $currentDocument?->recipient_email) }}" maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Fecha
                            <input name="document_date" type="date" value="{{ old('document_date', $currentDocument?->document_date?->toDateString() ?? now()->toDateString()) }}" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Vencimiento
                            <input name="due_date" type="date" value="{{ old('due_date', $currentDocument?->due_date?->toDateString()) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        </label>
                        @if ($isNote)
                            <label class="text-sm font-medium text-gray-700 sm:col-span-2">
                                Factura origen
                                <select name="reference_invoice_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                    <option value="">Selecciona la factura afectada</option>
                                    @foreach ($referenceInvoices as $referenceInvoice)
                                        @php
                                            $referenceId = $type === 'customer_credit_note' ? $currentDocument?->reference_sale_id : $currentDocument?->reference_purchase_id;
                                            $referenceDate = $type === 'customer_credit_note' ? $referenceInvoice->sale_date : $referenceInvoice->purchase_date;
                                            $referenceTotal = $type === 'customer_credit_note' ? $referenceInvoice->total : $referenceInvoice->total_pagar;
                                            $referenceParty = $type === 'customer_credit_note' ? $referenceInvoice->customer_name : $referenceInvoice->provider;
                                        @endphp
                                        <option value="{{ $referenceInvoice->id }}" data-party="{{ $referenceParty }}" @selected((string) old('reference_invoice_id', $referenceId) === (string) $referenceInvoice->id)>
                                            {{ $referenceInvoice->invoice_number }} · {{ $referenceParty }} · {{ $referenceDate->format('d/m/Y') }} · ${{ number_format($referenceTotal, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="text-sm font-medium text-gray-700 sm:col-span-2 xl:col-span-3">
                            Notas
                            <input name="notes" value="{{ old('notes', $currentDocument?->notes) }}" maxlength="3000" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        </label>
                    </div>

                    <div class="overflow-x-auto border-y border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                                <tr>
                                    <th class="px-3 py-2">Producto</th>
                                    <th class="w-24 px-3 py-2">Cantidad</th>
                                    <th class="w-36 px-3 py-2">{{ $isPurchaseDocument ? 'Costo unitario' : 'Precio unitario' }}</th>
                                    <th class="w-28 px-3 py-2">IVA %</th>
                                    @if ($type !== 'purchase_order')
                                        <th class="w-28 px-3 py-2">Descuento %</th>
                                    @else
                                        <th class="w-28 px-3 py-2">Utilidad %</th>
                                    @endif
                                    <th class="w-32 px-3 py-2 text-right">Total línea</th>
                                    <th class="w-12 px-3 py-2"><span class="sr-only">Quitar</span></th>
                                </tr>
                            </thead>
                            <tbody id="document-lines" class="divide-y divide-gray-100">
                                @foreach ($formLines as $index => $line)
                                    <tr class="document-line">
                                        <td class="min-w-56 px-3 py-2">
                                            <select name="items[{{ $index }}][item_id]" required class="item-select block w-full rounded-md border-gray-300 text-sm">
                                                <option value="">Selecciona producto</option>
                                                @foreach ($items as $item)
                                                    <option value="{{ $item->id }}" data-sale-price="{{ $item->sale_price }}" data-purchase-price="{{ $item->purchase_price }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2"><input name="items[{{ $index }}][quantity]" type="number" min="1" max="1000000" value="{{ $line['quantity'] ?? 1 }}" required class="quantity block w-full rounded-md border-gray-300 text-sm"></td>
                                        <td class="px-3 py-2"><input name="items[{{ $index }}][unit_price]" type="number" min="0" max="999999999" step="0.01" value="{{ $line['unit_price'] ?? '' }}" required class="unit-price block w-full rounded-md border-gray-300 text-sm"></td>
                                        <td class="px-3 py-2">
                                            <select name="items[{{ $index }}][iva_percentage]" class="iva-percentage block w-full rounded-md border-gray-300 text-sm">
                                                @foreach ([0, 5, 19] as $rate)
                                                    <option value="{{ $rate }}" @selected((float) ($line['iva_percentage'] ?? 19) === (float) $rate)>{{ $rate }}%</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            @if ($type !== 'purchase_order')
                                                <input name="items[{{ $index }}][discount_percentage]" type="number" min="0" max="100" step="0.01" value="{{ $line['discount_percentage'] ?? 0 }}" class="discount-percentage block w-full rounded-md border-gray-300 text-sm">
                                            @else
                                                <input name="items[{{ $index }}][utility_percentage]" type="number" min="0" max="1000" step="0.01" value="{{ $line['utility_percentage'] ?? 0 }}" class="utility-percentage block w-full rounded-md border-gray-300 text-sm">
                                            @endif
                                        </td>
                                        <td class="line-total px-3 py-2 text-right font-semibold tabular-nums">$0.00</td>
                                        <td class="px-3 py-2 text-right"><button type="button" class="remove-line rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700" aria-label="Quitar renglón">×</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <button id="add-line" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">＋ Agregar renglón</button>
                        <dl class="grid min-w-64 grid-cols-2 gap-x-8 gap-y-1 text-sm">
                            <dt class="text-gray-600">Subtotal</dt><dd id="subtotal" class="text-right font-medium tabular-nums">$0.00</dd>
                            @if ($type !== 'purchase_order')
                                <dt class="text-gray-600">Descuentos</dt><dd id="discount-total" class="text-right font-medium tabular-nums">$0.00</dd>
                            @endif
                            <dt class="text-gray-600">IVA</dt><dd id="iva-total" class="text-right font-medium tabular-nums">$0.00</dd>
                            <dt class="border-t border-gray-300 pt-2 font-bold text-gray-900">Total</dt><dd id="grand-total" class="border-t border-gray-300 pt-2 text-right font-bold tabular-nums text-teal-800">$0.00</dd>
                        </dl>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 pt-4">
                        @if ($currentDocument)
                            <a href="{{ route($configuration['indexRoute']) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Nuevo</a>
                        @endif
                        <button type="submit" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-bold text-white hover:bg-teal-800">💾 {{ $configuration['saveLabel'] }}</button>
                    </div>
                </form>

                @if ($currentDocument)
                    <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                        <form method="POST" action="{{ route('commercial-documents.email', $currentDocument) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="text-xs font-semibold text-gray-600">Enviar por correo
                                <input name="email" type="email" value="{{ $currentDocument->recipient_email }}" required placeholder="correo@empresa.com" class="mt-1 block rounded-md border-gray-300 text-sm">
                            </label>
                            <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700">✉ Enviar</button>
                        </form>
                        <details class="relative">
                            <summary class="cursor-pointer list-none rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700">Más ▾</summary>
                            <div class="absolute right-0 z-10 mt-1 grid min-w-36 rounded-md border border-gray-200 bg-white p-1 shadow-lg">
                                <a target="_blank" href="{{ route('commercial-documents.print', $currentDocument) }}" class="rounded px-3 py-2 text-sm hover:bg-gray-50">Imprimir</a>
                                <a href="{{ route('commercial-documents.download', $currentDocument) }}" class="rounded px-3 py-2 text-sm hover:bg-gray-50">Descargar CSV</a>
                            </div>
                        </details>
                        @if ($currentDocument->status === 'draft')
                            @if ($isNote)
                                <a href="{{ route('accounting.vouchers.index', ['commercial_document_id' => $currentDocument->id]) }}" class="rounded-md bg-teal-700 px-3 py-2 text-sm font-semibold text-white">Contabilizar</a>
                                <a href="{{ route('commercial-documents.statement', $currentDocument) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700">Ver estado de cuenta</a>
                            @elseif (isset($configuration['convertLabel']))
                                <form method="POST" action="{{ route('commercial-documents.convert', $currentDocument) }}" onsubmit="return confirm('¿Convertir este documento y aplicar sus movimientos de inventario?')">
                                    @csrf
                                    <button class="rounded-md bg-amber-600 px-3 py-2 text-sm font-bold text-white hover:bg-amber-700">{{ $configuration['convertLabel'] }}</button>
                                </form>
                            @endif
                        @endif
                    </div>
                @endif
            </section>

            <section aria-labelledby="document-history-title" class="pt-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 id="document-history-title" class="text-lg font-bold text-gray-900">Documentos guardados</h2>
                    <span class="text-sm text-gray-500">{{ $documents->total() }} en este módulo</span>
                </div>

                <div class="overflow-x-auto border-y border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Consecutivo</th>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">{{ $configuration['partyLabel'] }}</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($documents as $document)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-3">
                                        @if ($configuration['editConsecutive'] && $document->status === 'draft')
                                            <form method="POST" action="{{ route('commercial-documents.consecutive', $document) }}" class="flex items-center gap-1">
                                                @csrf
                                                @method('PATCH')
                                                <input name="consecutive" value="{{ $document->consecutive }}" maxlength="60" required aria-label="Editar consecutivo {{ $document->consecutive }}" class="w-32 rounded border-gray-300 text-sm">
                                                <button title="Guardar consecutivo" class="rounded border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700">Guardar</button>
                                            </form>
                                        @else
                                            <span class="font-semibold text-gray-900">{{ $document->consecutive }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ $document->document_date->format('d/m/Y') }}</td>
                                    <td class="px-3 py-3">
                                        {{ $document->third_party_name }}
                                        @if ($isNote)
                                            <span class="block text-xs text-gray-500">Factura origen: {{ $document->referenceSale?->invoice_number ?? $document->referencePurchase?->invoice_number ?? 'Sin referencia' }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">${{ number_format($document->total, 2) }}</td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ in_array($document->status, ['converted', 'accounted'], true) ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $document->status === 'converted' ? 'Convertido' : ($document->status === 'accounted' ? 'Contabilizada' : 'Borrador') }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @if ($document->status === 'draft')
                                                <a href="{{ route($configuration['indexRoute'], ['document' => $document->id]) }}" class="font-semibold text-teal-800 hover:underline">Editar</a>
                                                <form method="POST" action="{{ route('commercial-documents.email', $document) }}" class="flex items-center gap-1">
                                                    @csrf
                                                    <input name="email" type="email" value="{{ $document->recipient_email }}" required aria-label="Correo para {{ $document->consecutive }}" placeholder="Correo" class="w-36 rounded border-gray-300 text-xs">
                                                    <button class="rounded border border-gray-300 px-2 py-1 text-xs">Enviar</button>
                                                </form>
                                                @if ($isNote)
                                                    <a href="{{ route('accounting.vouchers.index', ['commercial_document_id' => $document->id]) }}" class="rounded bg-teal-700 px-2 py-1 text-xs font-semibold text-white">Contabilizar</a>
                                                @elseif (isset($configuration['convertLabel']))
                                                    <form method="POST" action="{{ route('commercial-documents.convert', $document) }}" onsubmit="return confirm('¿{{ $configuration['convertLabel'] }} y aplicar movimientos de inventario?')">
                                                        @csrf
                                                        <button class="rounded bg-amber-600 px-2 py-1 text-xs font-semibold text-white">{{ $configuration['convertLabel'] }}</button>
                                                    </form>
                                                @endif
                                            @endif
                                            @if ($document->accountingVoucher)
                                                <a href="{{ route('accounting.vouchers.accounting', $document->accountingVoucher) }}" class="rounded border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700">Ver contabilización</a>
                                            @endif
                                            <details class="relative">
                                                <summary class="cursor-pointer list-none rounded border border-gray-300 px-2 py-1 text-xs font-semibold">Más ▾</summary>
                                                <div class="absolute right-0 z-10 mt-1 grid min-w-36 rounded-md border border-gray-200 bg-white p-1 shadow-lg">
                                                    <a target="_blank" href="{{ route('commercial-documents.print', $document) }}" class="rounded px-3 py-2 text-xs hover:bg-gray-50">Imprimir</a>
                                                    <a href="{{ route('commercial-documents.download', $document) }}" class="rounded px-3 py-2 text-xs hover:bg-gray-50">Descargar CSV</a>
                                                </div>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Todavía no hay documentos guardados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $documents->links() }}</div>
            </section>
        </div>
    </div>

    <script>
        const catalog = @json($items->values());
        const documentType = @json($type);
        const isPurchaseDocument = @json($isPurchaseDocument);
        let lineIndex = {{ count($formLines) }};

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, (character) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character]));
        }

        function itemOptions() {
            return '<option value="">Selecciona producto</option>' + catalog.map((item) => {
                return `<option value="${item.id}" data-sale-price="${item.sale_price}" data-purchase-price="${item.purchase_price ?? 0}">${escapeHtml(item.name)}</option>`;
            }).join('');
        }

        function addDocumentLine() {
            const purchaseOrder = documentType === 'purchase_order';
            const row = document.createElement('tr');
            row.className = 'document-line';
            row.innerHTML = `<td class="min-w-56 px-3 py-2"><select name="items[${lineIndex}][item_id]" required class="item-select block w-full rounded-md border-gray-300 text-sm">${itemOptions()}</select></td>
                <td class="px-3 py-2"><input name="items[${lineIndex}][quantity]" type="number" min="1" max="1000000" value="1" required class="quantity block w-full rounded-md border-gray-300 text-sm"></td>
                <td class="px-3 py-2"><input name="items[${lineIndex}][unit_price]" type="number" min="0" max="999999999" step="0.01" required class="unit-price block w-full rounded-md border-gray-300 text-sm"></td>
                <td class="px-3 py-2"><select name="items[${lineIndex}][iva_percentage]" class="iva-percentage block w-full rounded-md border-gray-300 text-sm"><option value="0">0%</option><option value="5">5%</option><option value="19" selected>19%</option></select></td>
                <td class="px-3 py-2">${purchaseOrder ? `<input name="items[${lineIndex}][utility_percentage]" type="number" min="0" max="1000" step="0.01" value="0" class="utility-percentage block w-full rounded-md border-gray-300 text-sm">` : `<input name="items[${lineIndex}][discount_percentage]" type="number" min="0" max="100" step="0.01" value="0" class="discount-percentage block w-full rounded-md border-gray-300 text-sm">`}</td>
                <td class="line-total px-3 py-2 text-right font-semibold tabular-nums">$0.00</td>
                <td class="px-3 py-2 text-right"><button type="button" class="remove-line rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700" aria-label="Quitar renglón">×</button></td>`;
            document.getElementById('document-lines').appendChild(row);
            lineIndex++;
            calculateTotals();
        }

        function fmt(n) {
            return (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function calculateTotals() {
            let subtotal = 0;
            let discountTotal = 0;
            let ivaTotal = 0;

            document.querySelectorAll('.document-line').forEach((row) => {
                const quantity = Number(row.querySelector('.quantity').value || 0);
                const unitPrice = Number(row.querySelector('.unit-price').value || 0);
                const discountPercentage = Number(row.querySelector('.discount-percentage')?.value || 0);
                const ivaPercentage = Number(row.querySelector('.iva-percentage').value || 0);
                const lineSubtotal = quantity * unitPrice;
                const discount = lineSubtotal * discountPercentage / 100;
                const iva = (lineSubtotal - discount) * ivaPercentage / 100;
                row.querySelector('.line-total').textContent = '$' + fmt(lineSubtotal - discount + iva);
                subtotal += lineSubtotal;
                discountTotal += discount;
                ivaTotal += iva;
            });

            document.getElementById('subtotal').textContent = '$' + fmt(subtotal);
            document.getElementById('iva-total').textContent = '$' + fmt(ivaTotal);
            document.getElementById('grand-total').textContent = '$' + fmt(subtotal - discountTotal + ivaTotal);
            const discountElement = document.getElementById('discount-total');
            if (discountElement) {
                discountElement.textContent = '$' + fmt(discountTotal);
            }
        }

        document.getElementById('add-line').addEventListener('click', addDocumentLine);
        document.addEventListener('input', (event) => {
            if (event.target.closest('.document-line')) {
                calculateTotals();
            }
        });
        document.addEventListener('change', (event) => {
            if (event.target.matches('.item-select')) {
                const option = event.target.selectedOptions[0];
                const price = isPurchaseDocument ? option?.dataset.purchasePrice : option?.dataset.salePrice;
                event.target.closest('.document-line').querySelector('.unit-price').value = price || '';
                calculateTotals();
            }
        });
        document.addEventListener('click', (event) => {
            if (event.target.matches('.remove-line')) {
                if (document.querySelectorAll('.document-line').length > 1) {
                    event.target.closest('.document-line').remove();
                    calculateTotals();
                }
            }
        });
        document.getElementById('third-party').addEventListener('change', (event) => {
            const option = event.target.selectedOptions[0];
            document.getElementById('recipient-email').value = option?.dataset.email || '';
            const referenceSelect = document.querySelector('[name="reference_invoice_id"]');
            if (referenceSelect) {
                Array.from(referenceSelect.options).forEach((referenceOption, index) => {
                    if (index > 0) {
                        referenceOption.hidden = referenceOption.dataset.party !== option?.dataset.name;
                    }
                });
            }
        });
        calculateTotals();
    </script>
</x-app-layout>