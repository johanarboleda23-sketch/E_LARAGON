<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-teal-700">{{ $partyLabel }}</p>
                <h2 class="text-xl font-bold text-gray-900">Estado de cuenta documental</h2>
            </div>
            <a href="{{ route($document->document_type === 'customer_credit_note' ? 'commercial-documents.customer-credit-notes' : 'commercial-documents.supplier-debit-notes') }}" class="rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700">Volver</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <section class="border-b border-gray-200 pb-5">
                <h1 class="text-lg font-bold text-gray-900">{{ $document->third_party_name }}</h1>
                <p class="mt-1 text-sm text-gray-600">Factura origen: <strong>{{ $referenceInvoice->invoice_number }}</strong></p>
                <p class="text-sm text-gray-600">Nota relacionada: {{ $document->consecutive }}</p>
                <p class="mt-4 text-sm text-amber-800">Este resumen muestra facturas y notas registradas. No incluye pagos o abonos no vinculados a este módulo.</p>
            </section>

            <div class="mt-5 overflow-x-auto border-y border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                        <tr>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Documento</th>
                            <th class="px-3 py-2">Concepto</th>
                            <th class="px-3 py-2 text-right">Débito</th>
                            <th class="px-3 py-2 text-right">Crédito</th>
                            <th class="px-3 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-3">{{ $transaction['date']->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-3 font-semibold">{{ $transaction['document'] }}</td>
                                <td class="px-3 py-3">{{ $transaction['description'] }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">${{ number_format($transaction['debit'], 2) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">${{ number_format($transaction['credit'], 2) }}</td>
                                <td class="px-3 py-3">{{ $transaction['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold text-gray-900">
                        <tr>
                            <td colspan="3" class="px-3 py-3">Saldo documental estimado</td>
                            <td colspan="3" class="px-3 py-3 text-right tabular-nums">${{ number_format($balance, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 flex justify-end print:hidden">
                <button type="button" onclick="window.print()" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700">Imprimir estado</button>
            </div>
        </div>
    </div>
</x-app-layout>