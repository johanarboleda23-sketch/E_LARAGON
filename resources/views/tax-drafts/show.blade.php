<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">SU+ GESTION EMPRESARIAL / Impuestos</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Borrador · {{ $label }}</h2>
            </div>
            <div class="flex gap-2 print:hidden">
                <a href="{{ route('tax-drafts.index') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Impuestos</a>
                <button type="button" onclick="window.print()" class="rounded-lg bg-[#227c70] px-3 py-2 text-xs font-bold text-white">🖨 Imprimir</button>
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8 print:bg-white">
        <div class="mx-auto max-w-4xl space-y-5">
            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm print:hidden">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <label class="text-xs font-bold text-[#52635b]">Desde
                        <input type="date" name="from" value="{{ $from->toDateString() }}" class="mt-1 block rounded-lg border-[#cbd6cf] text-sm">
                    </label>
                    <label class="text-xs font-bold text-[#52635b]">Hasta
                        <input type="date" name="to" value="{{ $to->toDateString() }}" class="mt-1 block rounded-lg border-[#cbd6cf] text-sm">
                    </label>
                    <button class="rounded-lg bg-[#227c70] px-4 py-2 text-xs font-bold text-white">Calcular periodo</button>
                </form>
            </section>

            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                ⚠ Borrador aproximado calculado con la información de la app para el periodo {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}. No reemplaza la asesoría de tu contador ni constituye una declaración oficial.
            </section>

            @if($type === 'iva')
                <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-[#e6ede8]">
                            <tr><td class="p-3 font-semibold">IVA generado en ventas</td><td class="p-3 text-right">${{ number_format($data['iva_generado_ventas'], 2) }}</td></tr>
                            <tr><td class="p-3 font-semibold">IVA generado en documentos soporte</td><td class="p-3 text-right">${{ number_format($data['iva_generado_doc_soporte'], 2) }}</td></tr>
                            <tr class="bg-[#f8faf8]"><td class="p-3 font-bold">Total IVA generado</td><td class="p-3 text-right font-bold">${{ number_format($data['iva_generado'], 2) }}</td></tr>
                            <tr><td class="p-3 font-semibold">IVA descontable en compras</td><td class="p-3 text-right">${{ number_format($data['iva_descontable'], 2) }}</td></tr>
                            <tr class="border-t-2 border-[#192522] bg-[#f8faf8] text-base"><td class="p-3 font-black">Saldo a pagar</td><td class="p-3 text-right font-black text-[#b65338]">${{ number_format($data['saldo_a_pagar'], 2) }}</td></tr>
                            <tr><td class="p-3 font-black">Saldo a favor</td><td class="p-3 text-right font-black text-[#227c70]">${{ number_format($data['saldo_a_favor'], 2) }}</td></tr>
                        </tbody>
                    </table>
                </section>
            @elseif($type === 'retencion')
                <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-[#e6ede8]">
                            <tr><td class="p-3 font-semibold">Retefuente practicada en compras</td><td class="p-3 text-right">${{ number_format($data['retefuente_practicada_compras'], 2) }}</td></tr>
                            <tr><td class="p-3 font-semibold">Autorretención en documentos soporte</td><td class="p-3 text-right">${{ number_format($data['autorretencion_documentos_soporte'], 2) }}</td></tr>
                            <tr class="border-t-2 border-[#192522] bg-[#f8faf8] text-base"><td class="p-3 font-black">Total a declarar y pagar</td><td class="p-3 text-right font-black text-[#b65338]">${{ number_format($data['total_a_declarar_y_pagar'], 2) }}</td></tr>
                            <tr><td class="p-3 text-[#71807a]">Informativo: retención que nos practicaron en ventas (no se paga, es un anticipo a tu favor)</td><td class="p-3 text-right text-[#71807a]">${{ number_format($data['retencion_que_nos_practicaron_ventas'], 2) }}</td></tr>
                        </tbody>
                    </table>
                </section>
            @elseif($type === 'ica')
                <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                    @unless($data['tasa_configurada'])
                        <div class="bg-red-50 p-4 text-xs font-semibold text-red-700">Configura la tarifa de ICA (por mil) de tu municipio en Administración para que este cálculo sea exacto.</div>
                    @endunless
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-[#e6ede8]">
                            <tr><td class="p-3 font-semibold">Ingresos gravados (ventas + documentos soporte)</td><td class="p-3 text-right">${{ number_format($data['ingresos_gravados'], 2) }}</td></tr>
                            <tr><td class="p-3 font-semibold">Tarifa configurada (por mil)</td><td class="p-3 text-right">{{ number_format($data['tasa_por_mil'], 2) }}</td></tr>
                            <tr class="border-t-2 border-[#192522] bg-[#f8faf8] text-base"><td class="p-3 font-black">Impuesto a cargo</td><td class="p-3 text-right font-black text-[#b65338]">${{ number_format($data['impuesto_a_cargo'], 2) }}</td></tr>
                        </tbody>
                    </table>
                </section>
            @elseif($type === 'renta')
                <section class="overflow-hidden rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-[#e6ede8]">
                            <tr><td class="p-3 font-semibold">Ingresos (ventas + documentos soporte)</td><td class="p-3 text-right">${{ number_format($data['ingresos'], 2) }}</td></tr>
                            <tr><td class="p-3 font-semibold">Costos (compras)</td><td class="p-3 text-right">-${{ number_format($data['costos_compras'], 2) }}</td></tr>
                            <tr><td class="p-3 font-semibold">Gastos contabilizados (clases 5, 6 y 7 del PUC)</td><td class="p-3 text-right">-${{ number_format($data['gastos_contables'], 2) }}</td></tr>
                            <tr class="bg-[#f8faf8]"><td class="p-3 font-bold">Utilidad antes de impuesto</td><td class="p-3 text-right font-bold">${{ number_format($data['utilidad_antes_de_impuesto'], 2) }}</td></tr>
                            <tr><td class="p-3 font-semibold">Tarifa de renta configurada</td><td class="p-3 text-right">{{ number_format($data['tasa_renta'], 2) }}%</td></tr>
                            <tr class="border-t-2 border-[#192522] bg-[#f8faf8] text-base"><td class="p-3 font-black">Impuesto de renta estimado</td><td class="p-3 text-right font-black text-[#b65338]">${{ number_format($data['impuesto_estimado'], 2) }}</td></tr>
                        </tbody>
                    </table>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
