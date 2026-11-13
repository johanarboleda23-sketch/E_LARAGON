<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Ficha del tercero</p>
                <h2 class="mt-1 text-xl font-bold text-[#192522]">{{ $thirdParty->name }}</h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('third-parties.index') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-4 py-2 text-sm font-semibold text-[#192522]">Volver a terceros</a>
                <a href="{{ route('third-parties.index', ['edit' => $thirdParty->id]) }}" class="rounded-lg bg-[#227c70] px-4 py-2 text-sm font-bold text-white">Editar tercero</a>
            </div>
        </div>
    </x-slot>

    <main class="min-h-screen bg-[#f3f5f1] px-4 py-6 sm:px-8">
        <div class="mx-auto grid max-w-5xl gap-5 lg:grid-cols-[1fr_320px]">
            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#e5ebe6] pb-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">{{ $thirdParty->type === 'juridica' ? 'Persona jurídica' : 'Persona natural' }}</p>
                        <h1 class="mt-1 text-2xl font-black text-[#192522]">{{ $thirdParty->name }}</h1>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $thirdParty->active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ $thirdParty->active ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>

                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Documento / NIT</dt>
                        <dd class="mt-1 text-sm font-medium text-[#192522]">
                            {{ $thirdParty->document ?: 'No registrado' }}@if ($thirdParty->verification_digit !== null)-{{ $thirdParty->verification_digit }}@endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Correo electrónico</dt>
                        <dd class="mt-1 text-sm text-[#192522]">{{ $thirdParty->email ?: 'No registrado' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Teléfono</dt>
                        <dd class="mt-1 text-sm text-[#192522]">{{ $thirdParty->phone ?: 'No registrado' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Dirección</dt>
                        <dd class="mt-1 text-sm text-[#192522]">{{ $thirdParty->address ?: 'No registrada' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Barrio</dt>
                        <dd class="mt-1 text-sm text-[#192522]">{{ $thirdParty->neighborhood ?: 'No registrado' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Ciudad</dt>
                        <dd class="mt-1 text-sm text-[#192522]">{{ $thirdParty->city ?: 'No registrada' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Responsabilidades fiscales (RUT, casilla 53)</dt>
                        <dd class="mt-1 text-sm text-[#192522]">{{ implode(', ', $thirdParty->fiscal_responsibilities ?? []) ?: 'No registradas' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Perfiles</dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            @if ($thirdParty->is_customer)
                                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800">Cliente</span>
                            @endif
                            @if ($thirdParty->is_supplier)
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Proveedor</span>
                            @endif
                            @if ($thirdParty->is_employee)
                                <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-800">Empleado</span>
                            @endif
                        </dd>
                    </div>
                    @if ($thirdParty->contracts->isNotEmpty())
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Contratos</dt>
                            <dd class="mt-2 space-y-2">
                                @foreach ($thirdParty->contracts as $contract)
                                    <div class="flex flex-wrap justify-between gap-2 rounded-lg bg-[#f3f5f1] p-3 text-sm">
                                        <span class="font-semibold text-[#192522]">{{ $contract->consecutive }}</span>
                                        <span class="text-[#71807a]">{{ $contract->contract_type }} · {{ $contract->active ? 'Activo' : 'Inactivo' }}</span>
                                    </div>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            <aside class="h-fit rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                <h2 class="font-bold text-[#192522]">Registro del sistema</h2>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Creado por</dt>
                        <dd class="mt-1 text-sm font-semibold text-[#192522]">{{ $thirdParty->createdBy?->name ?? 'No disponible para este registro' }}</dd>
                        @if ($thirdParty->createdBy?->email)
                            <dd class="text-xs text-[#71807a]">{{ $thirdParty->createdBy->email }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Fecha de creación</dt>
                        <dd class="mt-1 text-sm text-[#192522]">{{ $thirdParty->created_at?->format('d/m/Y H:i') ?? 'No disponible' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Última actualización</dt>
                        <dd class="mt-1 text-sm text-[#192522]">{{ $thirdParty->updated_at?->format('d/m/Y H:i') ?? 'No disponible' }}</dd>
                    </div>
                </dl>
            </aside>
        </div>
    </main>
</x-app-layout>
