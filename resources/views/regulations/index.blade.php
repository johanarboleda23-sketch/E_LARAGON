<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-[#d96b4c]">SU+ GESTION EMPRESARIA / Cumplimiento</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-[#192522]">Centro de consulta normativa</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-6 sm:px-8">
        <div class="mx-auto max-w-6xl">
            <section class="rounded-2xl bg-[#227c70] p-6 text-white shadow-lg shadow-[#227c70]/15 sm:p-8">
                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-[#b8e3d7]">Normativa colombiana</p>
                <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Busca por palabra clave o sector</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#d8f0e8]">Este centro reúne enlaces a fuentes oficiales para temas contables, financieros, económicos, de revisoría fiscal, laborales y de seguridad social.</p>
                <p class="mt-3 max-w-2xl rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold text-[#fff8df]">⚠ Esta sección no constituye asesoría legal, contable ni tributaria. Verifica siempre la norma vigente en la fuente oficial.</p>
            </section>

            <form method="GET" action="{{ route('regulations.index') }}" class="mt-5 grid gap-3 rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm sm:grid-cols-[1fr_220px_auto] sm:items-end">
                <label class="text-xs font-bold text-[#52635b]">Palabra clave
                    <input type="search" name="q" value="{{ $query }}" placeholder="Ej. horario laboral, NIIF, PILA..." class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                </label>
                <label class="text-xs font-bold text-[#52635b]">Sector
                    <select name="sector" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                        <option value="">Todos los sectores</option>
                        @foreach($sectors as $value => $label)
                            <option value="{{ $value }}" @selected($selectedSector === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-lg bg-[#227c70] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#1b655c]">Buscar</button>
            </form>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @forelse($results as $item)
                    <article class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                        <span class="rounded-full bg-[#edf7f4] px-3 py-1 text-xs font-bold text-[#227c70]">{{ $sectors[$item['sector']] }}</span>
                        <h2 class="mt-3 text-lg font-black text-[#192522]">{{ $item['title'] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-[#52635b]">{{ $item['description'] }}</p>
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-[#227c70]">Ver fuente oficial ({{ $item['source'] }}) ↗</a>
                    </article>
                @empty
                    <p class="md:col-span-2 rounded-xl border border-dashed border-[#c7d2cb] bg-white p-8 text-center text-sm text-[#71807a]">No encontramos normativa con esa búsqueda.</p>
                @endforelse
            </div>

            <section class="mx-auto mt-6 grid max-w-3xl gap-4 sm:grid-cols-2">
                <a href="https://enlace.com.co/" target="_blank" rel="noopener noreferrer" class="rounded-2xl bg-[#192522] p-5 text-white transition hover:bg-[#263b36]">
                    <span class="text-2xl text-[#f5c96a]">⇪</span>
                    <p class="mt-5 font-black">Enlace Operativo</p>
                    <p class="mt-1 text-xs text-[#b9c9c1]">Plataforma externa para planillas y aportes.</p>
                </a>
                <a href="https://www.suaporte.com.co/" target="_blank" rel="noopener noreferrer" class="rounded-2xl bg-[#d96b4c] p-5 text-white transition hover:bg-[#bd583c]">
                    <span class="text-2xl">⇪</span>
                    <p class="mt-5 font-black">SuAporte / PILA</p>
                    <p class="mt-1 text-xs text-[#ffe4d8]">Validación y pago de planilla de seguridad social.</p>
                </a>
            </section>
        </div>
    </div>
</x-app-layout>
