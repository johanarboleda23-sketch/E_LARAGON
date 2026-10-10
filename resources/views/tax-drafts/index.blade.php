<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d96b4c]">SU+ GESTION EMPRESARIAL / Impuestos</p>
                <h2 class="mt-1 text-xl font-black text-[#192522]">Borradores de impuestos</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-5xl space-y-5">
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                ⚠ Estos borradores se calculan automáticamente con la información registrada en la app (ventas, compras, documentos soporte y comprobantes contables). Úsalos como punto de partida para preparar tu declaración; antes de presentarla ante la DIAN, revísalos con tu contador.
            </section>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($types as $key => $label)
                    <a href="{{ route('tax-drafts.show', $key) }}" class="rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-[#227c70]">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#d96b4c]">Borrador</p>
                        <h3 class="mt-1 text-lg font-black text-[#192522]">{{ $label }}</h3>
                        <p class="mt-2 text-xs text-[#71807a]">Calcular con base en el periodo que elijas.</p>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
