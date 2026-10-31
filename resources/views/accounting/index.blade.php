<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-[#d96b4c]">SU+ Gestión Empresarial / Finanzas</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-[#192522]">Contabilidad</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-xl border border-[#d7dfd8] bg-white px-4 py-2.5 text-sm font-bold text-[#227c70] transition hover:bg-[#f3f5f1]">
                Volver al tablero
            </a>
        </div>
    </x-slot>

    <main class="min-h-[calc(100vh-65px)] bg-[#f3f5f1] px-4 py-10 sm:px-8">
        <div class="mx-auto max-w-6xl">
            <section class="relative overflow-hidden rounded-3xl bg-[#227c70] px-6 py-8 text-white shadow-lg shadow-[#227c70]/15 sm:px-10 sm:py-10">
                <div class="relative z-10 max-w-2xl">
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-[#b8e3d7]">Centro contable</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">¿Qué comprobante necesitas?</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[#d8f0e8]">
                        Elige un flujo para registrar movimientos o consultar el historial de la empresa.
                    </p>
                </div>
                <div class="absolute -right-12 -top-20 h-56 w-56 rounded-full border-[22px] border-white/10 sm:h-72 sm:w-72"></div>
                <div class="absolute -bottom-28 right-32 h-48 w-48 rounded-full bg-white/5"></div>
            </section>

            <section class="mt-8">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#8b9992]">Accesos directos</p>
                        <h2 class="mt-1 text-lg font-black text-[#192522]">Selecciona una opción</h2>
                    </div>
                    <span class="text-xs font-medium text-[#71807a]">Cada opción abre su espacio de trabajo</span>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                    <a href="{{ route('accounting.vouchers.index.typed', 'egreso') }}" class="group flex min-h-56 flex-col rounded-2xl border border-[#eadbd4] bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-[#d96b4c] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#d96b4c] focus:ring-offset-2">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#fbefea] text-2xl text-[#b65338]" aria-hidden="true">↗</span>
                        <h3 class="mt-5 text-xl font-black text-[#192522]">Egreso</h3>
                        <p class="mt-2 text-sm leading-6 text-[#71807a]">Registra gastos, pagos y salidas de dinero con sus cuentas contables.</p>
                        <span class="mt-auto flex items-center gap-2 pt-5 text-sm font-bold text-[#b65338]">
                            Abrir egresos <span class="transition group-hover:translate-x-1" aria-hidden="true">→</span>
                        </span>
                    </a>

                    <a href="{{ route('accounting.vouchers.index.typed', 'recibo_caja') }}" class="group flex min-h-56 flex-col rounded-2xl border border-[#d7e8e2] bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-[#227c70] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#227c70] focus:ring-offset-2">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#edf7f4] text-2xl text-[#227c70]" aria-hidden="true">↓</span>
                        <h3 class="mt-5 text-xl font-black text-[#192522]">Recibo de caja</h3>
                        <p class="mt-2 text-sm leading-6 text-[#71807a]">Registra ingresos recibidos y consulta los recibos guardados.</p>
                        <span class="mt-auto flex items-center gap-2 pt-5 text-sm font-bold text-[#227c70]">
                            Abrir recibos <span class="transition group-hover:translate-x-1" aria-hidden="true">→</span>
                        </span>
                    </a>

                    <a href="{{ route('accounting.vouchers.index') }}" class="group flex min-h-56 flex-col rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-[#192522] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#227c70] focus:ring-offset-2">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f1f4f2] text-2xl text-[#192522]" aria-hidden="true">▤</span>
                        <h3 class="mt-5 text-xl font-black text-[#192522]">Comprobantes contables</h3>
                        <p class="mt-2 text-sm leading-6 text-[#71807a]">Crea otros comprobantes y revisa el historial contable completo.</p>
                        <span class="mt-auto flex items-center gap-2 pt-5 text-sm font-bold text-[#227c70]">
                            Ver comprobantes <span class="transition group-hover:translate-x-1" aria-hidden="true">→</span>
                        </span>
                    </a>
                </div>
            </section>

            <a href="{{ route('accounting.puc.index') }}" class="mt-6 flex flex-col gap-3 rounded-2xl border border-[#d7dfd8] bg-white px-5 py-4 shadow-sm transition hover:border-[#227c70] sm:flex-row sm:items-center sm:justify-between">
                <span class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#f3f5f1] text-lg" aria-hidden="true">▦</span>
                    <span>
                        <span class="block text-sm font-black text-[#192522]">Plan Único de Cuentas (PUC)</span>
                        <span class="mt-0.5 block text-xs text-[#71807a]">Consulta y administra las cuentas contables.</span>
                    </span>
                </span>
                <span class="text-sm font-bold text-[#227c70]">Abrir PUC →</span>
            </a>
        </div>
    </main>
</x-app-layout>
