<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-[#d96b4c]">SU+ GESTION EMPRESARIA / Personas</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-[#192522]">Nómina</h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
                <a href="{{ route('third-parties.index') }}" class="rounded-lg bg-[#192522] px-3 py-2 text-xs font-bold text-white">Terceros</a>
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-6 sm:px-8">
        <div class="mx-auto max-w-7xl">
            <section class="rounded-2xl bg-[#227c70] p-6 text-white shadow-lg shadow-[#227c70]/15 sm:p-8">
                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-[#b8e3d7]">Centro de gestión laboral</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight">Una nómina, todos sus procesos.</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#d8f0e8]">Calcula, revisa y prepara los aportes de tu equipo desde una sola pantalla.</p>
            </section>

            <nav class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8" aria-label="Submódulos de nómina">
                @foreach([
                    ['label' => 'Liquidación', 'icon' => '◎', 'href' => '#liquidacion', 'active' => true],
                    ['label' => 'Seguridad social', 'icon' => '＋', 'href' => '#seguridad-social', 'active' => false],
                    ['label' => 'Novedades', 'icon' => '△', 'href' => '#novedades', 'active' => false],
                    ['label' => 'Prestaciones', 'icon' => '◇', 'href' => '#prestaciones', 'active' => false],
                    ['label' => 'Contabilización', 'icon' => '▥', 'href' => '#contabilizacion', 'active' => false],
                    ['label' => 'Desprendibles', 'icon' => '▤', 'href' => '#desprendibles', 'active' => false],
                    ['label' => 'Historial', 'icon' => '◷', 'href' => '#historial', 'active' => false],
                    ['label' => 'Configuración', 'icon' => '⚙', 'href' => '#configuracion', 'active' => false],
                ] as $module)
                    <a href="{{ $module['href'] }}" class="rounded-xl border {{ $module['active'] ? 'border-[#227c70] bg-[#227c70] text-white' : 'border-[#d7dfd8] bg-white text-[#354d45]' }} p-3 transition hover:-translate-y-0.5 hover:border-[#227c70]">
                        <span class="text-lg">{{ $module['icon'] }}</span>
                        <span class="mt-2 block text-xs font-bold leading-4">{{ $module['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            @if(session('success'))
                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
            @endif

            <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_360px]">
                <section id="liquidacion" class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#d96b4c]">01 / Liquidación</p><h2 class="mt-1 text-xl font-black text-[#192522]">Calcular nómina</h2><p class="mt-1 text-xs text-[#71807a]">Revisa los valores antes de contabilizar o generar seguridad social.</p></div>
                        <span class="rounded-full bg-[#fff8df] px-3 py-1 text-xs font-bold text-[#8b6811]">Borrador de cálculo</span>
                    </div>
                    <form method="POST" action="{{ route('payroll.calculate') }}" class="mt-6">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-bold text-[#52635b]">Periodo<input type="month" name="period" required value="{{ now()->format('Y-m') }}" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm"></label><label class="text-xs font-bold text-[#52635b]">Fecha de pago<input type="date" name="payment_date" required value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm"></label></div>
                        <div class="mt-6 overflow-x-auto rounded-xl border border-[#d7dfd8]"><table class="w-full text-left text-sm"><thead class="bg-[#192522] text-xs uppercase text-white"><tr><th class="p-3">Empleado</th><th class="p-3">Salario mensual</th></tr></thead><tbody class="divide-y divide-[#e6ede8]">@forelse($employees as $index => $employee)<tr><td class="p-3 font-semibold text-[#192522]">{{ $employee->name }}<input type="hidden" name="employees[{{ $index }}][third_party_id]" value="{{ $employee->id }}"></td><td class="p-3"><input name="employees[{{ $index }}][salary]" type="number" min="0" step="0.01" required class="w-full rounded-lg border-[#cbd6cf] text-sm"></td></tr>@empty<tr><td colspan="2" class="p-4 text-[#71807a]">Registra terceros con perfil Empleado primero.</td></tr>@endforelse</tbody></table></div>
                        <button class="mt-5 rounded-lg bg-[#227c70] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#1b655c]">Calcular automáticamente</button>
                    </form>
                </section>

                <aside id="seguridad-social" class="rounded-2xl bg-[#192522] p-5 text-white sm:p-6">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#f5c96a]">02 / Seguridad social</p>
                    <h2 class="mt-2 text-xl font-black">Preparar la PILA</h2>
                    <p class="mt-3 text-sm leading-6 text-[#b9c9c1]">El archivo de preparación ya incluye EPS, AFP, ARL (por clase de riesgo) y caja de compensación de cada empleado, calculados automáticamente desde su contrato.</p>
                    <div class="mt-6 grid gap-3 text-xs"><div class="flex justify-between border-b border-white/10 pb-3"><span class="text-[#b9c9c1]">Afiliaciones</span><span class="font-bold text-[#86c8bb]">Desde el contrato</span></div><div class="flex justify-between border-b border-white/10 pb-3"><span class="text-[#b9c9c1]">Archivo plano</span><span class="font-bold text-[#f5c96a]">Descarga manual</span></div><div class="flex justify-between"><span class="text-[#b9c9c1]">Validación</span><span class="font-bold text-[#86c8bb]">Preparada</span></div></div>
                    @if($runs->isNotEmpty())
                        <a href="{{ route('payroll.social-security.file', $runs->first()) }}" class="mt-7 block rounded-lg bg-[#f5c96a] px-4 py-2.5 text-center text-sm font-bold text-[#263b36]">Descargar preparación SS</a>
                    @else
                        <p class="mt-7 rounded-lg border border-white/20 px-4 py-2.5 text-center text-xs text-[#b9c9c1]">Calcula una nómina para preparar la planilla.</p>
                    @endif
                    @if($runs->isNotEmpty())
                        <form method="POST" action="{{ route('payroll.social-security.errors.import', $runs->first()) }}" enctype="multipart/form-data" class="mt-3 grid gap-2">
                            @csrf
                            <input type="file" name="file" accept=".csv,.txt" class="text-xs text-[#b9c9c1]" required>
                            <button class="rounded-lg border border-white/20 px-4 py-2 text-xs font-bold text-white">Importar errores de Enlace</button>
                        </form>
                    @endif
                    @if($socialSecurityErrors->isNotEmpty())
                        <div class="mt-5 max-h-48 overflow-y-auto rounded-lg border border-white/10 text-xs">
                            @foreach($socialSecurityErrors as $error)
                                <div class="border-b border-white/10 p-2 last:border-0">
                                    <p class="font-bold text-[#f5c96a]">{{ $error->employee?->name ?? 'Sin identificar' }}@if($error->line_number) · Línea {{ $error->line_number }}@endif</p>
                                    <p class="mt-0.5 text-[#b9c9c1]">{{ $error->message }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </aside>
            </div>

            <section id="historial" class="mt-5 rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm sm:p-6"><div class="flex items-center justify-between gap-3"><div><p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#d96b4c]">03 / Historial</p><h2 class="mt-1 text-xl font-black text-[#192522]">Últimas nóminas</h2></div><span class="rounded-full bg-[#edf7f4] px-3 py-1 text-xs font-bold text-[#227c70]">{{ $runs->count() }} periodos</span></div><div class="mt-5 grid gap-3 md:grid-cols-2 lg:grid-cols-3">@forelse($runs as $run)<article class="rounded-xl border border-[#d7dfd8] bg-[#f8faf8] p-4"><div class="flex justify-between gap-3"><span class="font-black text-[#192522]">{{ $run->period }}</span><span class="text-xs font-bold text-[#227c70]">{{ ucfirst($run->status) }}</span></div><p class="mt-2 text-xs text-[#71807a]">Pago: {{ $run->payment_date->format('d/m/Y') }}</p><p class="mt-3 text-sm font-bold text-[#192522]">Neto: ${{ number_format($run->total_net, 2) }}</p>@if($run->accounting_voucher_id)<button type="button" class="mt-2 text-xs font-bold text-[#227c70]" onclick="openVoucherModal({{ $run->accounting_voucher_id }})">Asiento contable</button>@endif@foreach($run->lines as $line)<div class="mt-3 flex items-center justify-between border-t border-[#d7dfd8] pt-3 text-xs"><span>{{ $line->employee?->name }}</span>@if($line->employee?->email)<form method="POST" action="{{ route('payroll.email', $line) }}">@csrf<button class="font-bold text-[#227c70]">Enviar desprendible</button></form>@endif</div>@endforeach</article>@empty<p class="text-sm text-[#71807a]">Aún no hay nóminas calculadas.</p>@endforelse</div></section>

            <div class="sr-only" id="novedades">Novedades</div><div class="sr-only" id="prestaciones">Prestaciones</div><div class="sr-only" id="contabilizacion">Contabilización</div><div class="sr-only" id="desprendibles">Desprendibles</div><div class="sr-only" id="configuracion">Configuración</div>
        </div>
    </div>
    <x-voucher-modal />
</x-app-layout>
