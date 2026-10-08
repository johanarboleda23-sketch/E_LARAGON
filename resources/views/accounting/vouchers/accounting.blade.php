<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">Contabilidad / Partida doble</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Comprobante {{ $voucher->consecutive }}</h2>
            </div>
            <div class="flex gap-2 print:hidden">
                <a href="{{ route('accounting.vouchers.index') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Comprobantes</a>
                <button type="button" onclick="window.print()" class="rounded-lg bg-[#227c70] px-3 py-2 text-xs font-bold text-white hover:bg-[#1a5f55]">🖨 Imprimir</button>
            </div>
        </div>
    </x-slot>

    @php
        $totalDebit = (float) $voucher->total_debit;
        $totalCredit = (float) $voucher->total_credit;
        $isBalanced = round($totalDebit, 2) === round($totalCredit, 2);
    @endphp

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8 print:bg-white print:px-0 print:py-0">
        <div class="mx-auto max-w-4xl space-y-5">
            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm print:border-0 print:shadow-none">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="grid flex-1 gap-3 sm:grid-cols-2">
                        <div><p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Tipo de comprobante</p><p class="font-bold text-[#192522]">{{ strtoupper(str_replace('_', ' ', $voucher->voucher_type)) }}</p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Fecha</p><p class="font-bold text-[#192522]">{{ $voucher->voucher_date->format('d/m/Y') }}</p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Tercero</p><p class="font-bold text-[#192522]">{{ $voucher->third_party ?: '—' }}</p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Elaborado por</p><p class="font-bold text-[#192522]">{{ $voucher->creator?->name ?? 'Sistema' }}</p></div>
                        @if($voucher->description)
                            <div class="sm:col-span-2"><p class="text-[10px] font-bold uppercase tracking-wider text-[#8b9992]">Descripción</p><p class="text-sm text-[#364640]">{{ $voucher->description }}</p></div>
                        @endif
                    </div>
                    <span class="rounded-full px-3 py-1.5 text-xs font-black {{ $isBalanced ? 'bg-[#edf7f4] text-[#227c70]' : 'bg-red-50 text-red-700' }}">
                        {{ $isBalanced ? '✓ Partida doble cuadrada' : '⚠ Descuadrada' }}
                    </span>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm print:border-0 print:shadow-none">
                <table class="w-full text-left text-sm">
                    <thead class="bg-[#192522] text-xs uppercase text-white">
                        <tr>
                            <th class="p-3">Cuenta PUC</th>
                            <th class="p-3">Detalle</th>
                            <th class="p-3 text-right">Débito</th>
                            <th class="p-3 text-right">Crédito</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e6ede8]">
                        @foreach($voucher->lines as $line)
                            <tr>
                                <td class="p-3 font-mono text-xs font-bold text-[#192522]">{{ $line->account->code }} <span class="font-sans font-normal text-[#364640]">- {{ $line->account->name }}</span></td>
                                <td class="p-3 text-[#71807a]">{{ $line->detail ?: '—' }}</td>
                                <td class="p-3 text-right font-semibold {{ $line->debit > 0 ? 'text-[#227c70]' : 'text-[#c9d2cd]' }}">{{ $line->debit > 0 ? '$'.number_format($line->debit, 2) : '—' }}</td>
                                <td class="p-3 text-right font-semibold {{ $line->credit > 0 ? 'text-[#b65338]' : 'text-[#c9d2cd]' }}">{{ $line->credit > 0 ? '$'.number_format($line->credit, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-[#192522] bg-[#f8faf8] text-sm font-black text-[#192522]">
                            <td class="p-3" colspan="2">Totales</td>
                            <td class="p-3 text-right text-[#227c70]">${{ number_format($totalDebit, 2) }}</td>
                            <td class="p-3 text-right text-[#b65338]">${{ number_format($totalCredit, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>
