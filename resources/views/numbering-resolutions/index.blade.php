<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-[#d96b4c]">SU+ GESTION EMPRESARIA / Cumplimiento</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-[#192522]">Resoluciones de numeración</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-6 sm:px-8">
        <div class="mx-auto max-w-6xl">
            <section class="rounded-2xl bg-[#227c70] p-6 text-white shadow-lg shadow-[#227c70]/15 sm:p-8">
                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-[#b8e3d7]">Numeración autorizada</p>
                <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Asocia tus resoluciones DIAN</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#d8f0e8]">Registra el rango autorizado por cada resolución. Mientras esté activa, el sistema asignará el consecutivo automáticamente al guardar el documento.</p>
                <p class="mt-3 max-w-2xl rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold text-[#fff8df]">⚠ Si un tipo de documento no tiene resolución activa, el consecutivo se seguirá digitando manualmente.</p>
            </section>

            @if(session('success'))
                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('numbering-resolutions.store') }}" class="mt-5 grid gap-3 rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm sm:grid-cols-4">
                @csrf
                <label class="text-xs font-bold text-[#52635b]">Tipo de documento
                    <select name="document_type" required class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                        @foreach($documentTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-bold text-[#52635b]">Prefijo
                    <input type="text" name="prefix" value="{{ old('prefix') }}" maxlength="20" placeholder="FV" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                </label>
                <label class="text-xs font-bold text-[#52635b]">N° de resolución
                    <input type="text" name="resolution_number" value="{{ old('resolution_number') }}" required maxlength="50" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                </label>
                <label class="text-xs font-bold text-[#52635b]">Fecha de resolución
                    <input type="date" name="resolution_date" value="{{ old('resolution_date') }}" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                </label>
                <label class="text-xs font-bold text-[#52635b]">Vigente desde
                    <input type="date" name="valid_from" value="{{ old('valid_from') }}" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                </label>
                <label class="text-xs font-bold text-[#52635b]">Vigente hasta
                    <input type="date" name="valid_until" value="{{ old('valid_until') }}" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                </label>
                <label class="text-xs font-bold text-[#52635b]">Rango desde
                    <input type="number" name="range_from" value="{{ old('range_from', 1) }}" required min="1" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                </label>
                <label class="text-xs font-bold text-[#52635b]">Rango hasta
                    <input type="number" name="range_to" value="{{ old('range_to') }}" required min="1" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                </label>
                <button class="self-end rounded-lg bg-[#227c70] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#1b655c] sm:col-span-4 sm:w-fit">Registrar resolución</button>
            </form>

            <div class="mt-5 overflow-x-auto rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-[#192522] text-xs uppercase text-white">
                        <tr>
                            <th class="p-3">Tipo</th>
                            <th class="p-3">Prefijo</th>
                            <th class="p-3">Resolución</th>
                            <th class="p-3">Vigencia</th>
                            <th class="p-3">Rango</th>
                            <th class="p-3">Siguiente</th>
                            <th class="p-3">Estado</th>
                            <th class="p-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e6ede8]">
                        @forelse($resolutions as $resolution)
                            <tr>
                                <td class="p-3 font-semibold text-[#192522]">{{ $documentTypes[$resolution->document_type] ?? $resolution->document_type }}</td>
                                <td class="p-3 text-[#364640]">{{ $resolution->prefix ?: '—' }}</td>
                                <td class="p-3 text-[#364640]">{{ $resolution->resolution_number }}</td>
                                <td class="p-3 text-[#364640]">{{ $resolution->valid_from?->format('d/m/Y') ?? '—' }} - {{ $resolution->valid_until?->format('d/m/Y') ?? '—' }}</td>
                                <td class="p-3 text-[#364640]">{{ $resolution->range_from }} - {{ $resolution->range_to }}</td>
                                <td class="p-3 font-semibold text-[#192522]">{{ $resolution->next_number }}</td>
                                <td class="p-3">
                                    <span @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-[#edf7f4] text-[#227c70]' => $resolution->active, 'bg-[#fef2f2] text-[#b91c1c]' => ! $resolution->active])>{{ $resolution->active ? 'Activa' : 'Inactiva' }}</span>
                                </td>
                                <td class="p-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" onclick="document.getElementById('edit-row-{{ $resolution->id }}').classList.toggle('hidden')" class="text-xs font-bold text-[#192522] hover:underline">Editar</button>
                                        <form method="POST" action="{{ route('numbering-resolutions.update', $resolution) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="active" value="{{ $resolution->active ? 0 : 1 }}">
                                            <button class="text-xs font-bold text-[#227c70] hover:underline">{{ $resolution->active ? 'Desactivar' : 'Activar' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('numbering-resolutions.destroy', $resolution) }}" onsubmit="return confirm('¿Eliminar esta resolución?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-xs font-bold text-[#b91c1c] hover:underline">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr id="edit-row-{{ $resolution->id }}" class="hidden bg-[#f8faf8]">
                                <td colspan="8" class="p-3">
                                    <form method="POST" action="{{ route('numbering-resolutions.update', $resolution) }}" class="grid gap-2 sm:grid-cols-6">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="active" value="{{ $resolution->active ? 1 : 0 }}">
                                        <label class="text-xs font-bold text-[#52635b]">Prefijo
                                            <input type="text" name="prefix" value="{{ $resolution->prefix }}" maxlength="20" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                                        </label>
                                        <label class="text-xs font-bold text-[#52635b]">N° resolución
                                            <input type="text" name="resolution_number" value="{{ $resolution->resolution_number }}" required maxlength="50" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                                        </label>
                                        <label class="text-xs font-bold text-[#52635b]">Vigente desde
                                            <input type="date" name="valid_from" value="{{ $resolution->valid_from?->toDateString() }}" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                                        </label>
                                        <label class="text-xs font-bold text-[#52635b]">Vigente hasta
                                            <input type="date" name="valid_until" value="{{ $resolution->valid_until?->toDateString() }}" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                                        </label>
                                        <label class="text-xs font-bold text-[#52635b]">Rango desde
                                            <input type="number" name="range_from" value="{{ $resolution->range_from }}" required min="1" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                                        </label>
                                        <label class="text-xs font-bold text-[#52635b]">Rango hasta
                                            <input type="number" name="range_to" value="{{ $resolution->range_to }}" required min="1" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                                        </label>
                                        <label class="text-xs font-bold text-[#52635b] sm:col-span-2">Siguiente consecutivo
                                            <input type="number" name="next_number" value="{{ $resolution->next_number }}" required min="1" class="mt-1 w-full rounded-lg border-[#cbd6cf] text-sm">
                                        </label>
                                        <button class="self-end rounded-lg bg-[#192522] px-4 py-2 text-xs font-bold text-white sm:col-span-2">Guardar cambios</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-6 text-center text-sm text-[#71807a]">Aún no hay resoluciones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
