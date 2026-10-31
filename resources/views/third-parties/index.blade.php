<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-bold text-gray-800">Registro de terceros</h2>
            <div class="flex gap-2">
                <a href="{{ route('third-parties.template') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-700">⬇ Descargar plantilla</a>
                <button type="button" onclick="document.getElementById('third-party-import-modal').classList.remove('hidden')" class="rounded-lg bg-[#227c70] px-3 py-2 text-xs font-bold text-white">⇧ Migrar terceros</button>
            </div>
        </div>
    </x-slot>

    <div id="third-party-import-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-start justify-between">
                <h3 class="text-lg font-bold text-gray-800">Migrar terceros desde Excel</h3>
                <button type="button" onclick="document.getElementById('third-party-import-modal').classList.add('hidden')" class="text-gray-400">✕</button>
            </div>
            <p class="mb-3 text-xs text-gray-500">Usa nuestra plantilla para indicar si cada tercero es cliente, proveedor y/o empleado, junto con su documento, correo y teléfono. Máximo 500 registros por archivo.</p>
            <form method="POST" action="{{ route('third-parties.import') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="file" accept=".xlsx,.csv,.txt" required class="w-full rounded border-gray-300 text-sm">
                <button class="w-full rounded bg-[#227c70] px-3 py-2 text-sm font-bold text-white">Migrar terceros</button>
            </form>
        </div>
    </div>

    <div class="min-h-screen bg-gray-50 p-5">
        <div class="mx-auto grid max-w-7xl gap-5 lg:grid-cols-[340px_1fr]">
            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h3 class="mb-4 font-bold">Nuevo tercero</h3>
                @if (session('success'))
                    <div class="mb-3 rounded bg-emerald-50 p-2 text-sm text-emerald-700">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-3 rounded bg-red-50 p-3 text-sm text-red-700" role="alert">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div data-third-party-editor>
                    <form method="POST" action="{{ route('third-parties.store') }}" class="space-y-3">
                        @csrf
                        <input name="name" value="{{ old('name') }}" required placeholder="Nombre o razón social" class="w-full rounded border-gray-300 text-sm">
                        <input name="document" value="{{ old('document') }}" placeholder="Documento / NIT" class="w-full rounded border-gray-300 text-sm">
                        <select name="type" class="w-full rounded border-gray-300 text-sm">
                            <option value="natural" @selected(old('type', 'natural') === 'natural')>Persona natural</option>
                            <option value="juridica" @selected(old('type') === 'juridica')>Persona jurídica</option>
                        </select>
                        <input name="email" type="email" value="{{ old('email') }}" placeholder="Correo" class="w-full rounded border-gray-300 text-sm">
                        <input name="phone" value="{{ old('phone') }}" placeholder="Teléfono" class="w-full rounded border-gray-300 text-sm">
                        <input name="address" value="{{ old('address') }}" placeholder="Dirección de domicilio" class="w-full rounded border-gray-300 text-sm">
                        <input name="city" value="{{ old('city') }}" placeholder="Ciudad" class="w-full rounded border-gray-300 text-sm">
                        <input name="neighborhood" value="{{ old('neighborhood') }}" placeholder="Barrio" class="w-full rounded border-gray-300 text-sm">
                        <div class="space-y-2 text-sm">
                            <label class="block"><input type="checkbox" name="is_customer" value="1" @checked(old('is_customer'))> CLIENTE</label>
                            <label class="block"><input type="checkbox" name="is_supplier" value="1" @checked(old('is_supplier'))> PROVEEDOR</label>
                            <label class="block"><input type="checkbox" name="is_employee" value="1" @checked(old('is_employee'))> EMPLEADO</label>
                        </div>
                        <button class="w-full rounded bg-pink-600 px-3 py-2 text-sm font-bold text-white">Guardar tercero</button>
                    </form>
                    <form data-rut-upload class="mt-4 space-y-2 rounded-lg border border-dashed border-gray-300 p-3">
                        @csrf
                        <label class="block text-xs font-semibold text-gray-700">Autocompletar desde PDF del RUT</label>
                        <input type="file" name="rut_pdf" accept="application/pdf,.pdf" required class="w-full text-xs">
                        <button type="button" data-rut-extract data-url="{{ route('third-parties.rut.extract') }}" class="w-full rounded border border-[#227c70] px-3 py-2 text-xs font-bold text-[#227c70]">Leer PDF y completar datos</button>
                    </form>
                    <p data-rut-feedback class="mt-2 hidden text-xs" role="status" aria-live="polite"></p>
                </div>
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h3 class="mb-4 font-bold">Nuevo contrato de empleado</h3>
                <form method="POST" action="{{ route('employee-contracts.store') }}" class="grid gap-2 sm:grid-cols-2">
                    @csrf
                    <select name="third_party_id" required class="rounded border-gray-300 text-sm">
                        <option value="">Seleccione empleado</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                    <select name="contract_type" class="rounded border-gray-300 text-sm">
                        <option value="indefinido">Indefinido</option>
                        <option value="fijo">Término fijo</option>
                        <option value="obra_labor">Obra o labor</option>
                    </select>
                    <input name="start_date" type="date" required class="rounded border-gray-300 text-sm">
                    <input name="end_date" type="date" class="rounded border-gray-300 text-sm">
                    <input name="salary" type="number" min="0" step="0.01" required placeholder="Salario mensual" class="rounded border-gray-300 text-sm">
                    <input name="eps_name" placeholder="EPS" class="rounded border-gray-300 text-sm">
                    <input name="afp_name" placeholder="Fondo de pensión (AFP)" class="rounded border-gray-300 text-sm">
                    <select name="arl_risk_class" required class="rounded border-gray-300 text-sm">
                        <option value="1">ARL Clase I</option>
                        <option value="2">ARL Clase II</option>
                        <option value="3">ARL Clase III</option>
                        <option value="4">ARL Clase IV</option>
                        <option value="5">ARL Clase V</option>
                    </select>
                    <input name="compensation_fund_name" placeholder="Caja de compensación" class="rounded border-gray-300 text-sm">
                    <button class="rounded bg-violet-600 px-3 py-2 text-sm font-bold text-white sm:col-span-2">Crear contrato</button>
                </form>
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm lg:col-span-2">
                <h3 class="mb-4 font-bold">Terceros registrados</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-gray-50 text-xs uppercase">
                            <tr>
                                <th class="p-2">Nombre</th>
                                <th class="p-2">Documento</th>
                                <th class="p-2">Perfiles</th>
                                <th class="p-2">Contrato</th>
                                <th class="p-2">Correo</th>
                                <th class="p-2">Domicilio</th>
                                <th class="p-2">Estado</th>
                                <th class="p-2">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($thirdParties as $thirdParty)
                                <tr class="border-b">
                                    <td class="p-2 font-semibold">{{ $thirdParty->name }}</td>
                                    <td class="p-2">{{ $thirdParty->document }}</td>
                                    <td class="p-2">
                                        @if ($thirdParty->is_customer)
                                            <span class="mr-1 rounded bg-blue-100 px-1 text-xs text-blue-700">CLIENTE</span>
                                        @endif
                                        @if ($thirdParty->is_supplier)
                                            <span class="mr-1 rounded bg-amber-100 px-1 text-xs text-amber-700">PROVEEDOR</span>
                                        @endif
                                        @if ($thirdParty->is_employee)
                                            <span class="rounded bg-violet-100 px-1 text-xs text-violet-700">EMPLEADO</span>
                                        @endif
                                    </td>
                                    <td class="p-2">
                                        @foreach ($thirdParty->contracts as $contract)
                                            <div class="mb-1 text-xs">
                                                {{ $contract->consecutive }}
                                                <form class="inline" method="POST" action="{{ route('employee-contracts.toggle-active', $contract) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="ml-1 text-violet-600">{{ $contract->active ? 'Desactivar' : 'Activar' }}</button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </td>
                                    <td class="p-2">{{ $thirdParty->email }}</td>
                                    <td class="p-2">
                                        <div>{{ $thirdParty->address }}</div>
                                        <div class="text-xs text-gray-500">{{ collect([$thirdParty->neighborhood, $thirdParty->city])->filter()->implode(', ') }}</div>
                                    </td>
                                    <td class="p-2">{{ $thirdParty->active ? 'Activo' : 'Inactivo' }}</td>
                                    <td class="space-y-2 p-2">
                                        <button type="button" onclick="document.getElementById('edit-third-party-{{ $thirdParty->id }}').showModal()" class="rounded bg-blue-600 px-2 py-1 text-xs font-bold text-white">Editar</button>
                                        <form method="POST" action="{{ route('third-parties.toggle-active', $thirdParty) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="rounded px-2 py-1 text-xs font-bold text-white {{ $thirdParty->active ? 'bg-red-500' : 'bg-emerald-600' }}">{{ $thirdParty->active ? 'Desactivar' : 'Activar' }}</button>
                                        </form>
                                    </td>
                                </tr>
                                <dialog id="edit-third-party-{{ $thirdParty->id }}" class="w-full max-w-3xl rounded-xl p-0 shadow-xl backdrop:bg-black/50">
                                    <div class="max-h-[90vh] overflow-y-auto p-5">
                                        <div class="mb-4 flex items-center justify-between gap-4">
                                            <h3 class="text-lg font-bold text-gray-800">Editar tercero</h3>
                                            <button type="button" onclick="this.closest('dialog').close()" class="rounded border px-3 py-1 text-sm">Cerrar</button>
                                        </div>
                                        <div data-third-party-editor>
                                            <form method="POST" action="{{ route('third-parties.update', $thirdParty) }}" class="grid gap-3 sm:grid-cols-2">
                                                @csrf
                                                @method('PUT')
                                                <input name="name" value="{{ $thirdParty->name }}" required placeholder="Nombre o razón social" class="rounded border-gray-300 text-sm">
                                                <input name="document" value="{{ $thirdParty->document }}" placeholder="Documento / NIT" class="rounded border-gray-300 text-sm">
                                                <select name="type" class="rounded border-gray-300 text-sm">
                                                    <option value="natural" @selected($thirdParty->type === 'natural')>Persona natural</option>
                                                    <option value="juridica" @selected($thirdParty->type === 'juridica')>Persona jurídica</option>
                                                </select>
                                                <input name="email" type="email" value="{{ $thirdParty->email }}" placeholder="Correo" class="rounded border-gray-300 text-sm">
                                                <input name="phone" value="{{ $thirdParty->phone }}" placeholder="Teléfono" class="rounded border-gray-300 text-sm">
                                                <input name="address" value="{{ $thirdParty->address }}" placeholder="Dirección de domicilio" class="rounded border-gray-300 text-sm sm:col-span-2">
                                                <input name="city" value="{{ $thirdParty->city }}" placeholder="Ciudad" class="rounded border-gray-300 text-sm">
                                                <input name="neighborhood" value="{{ $thirdParty->neighborhood }}" placeholder="Barrio" class="rounded border-gray-300 text-sm">
                                                <div class="space-y-2 text-sm sm:col-span-2">
                                                    <label class="block"><input type="checkbox" name="is_customer" value="1" @checked($thirdParty->is_customer)> CLIENTE</label>
                                                    <label class="block"><input type="checkbox" name="is_supplier" value="1" @checked($thirdParty->is_supplier)> PROVEEDOR</label>
                                                    <label class="block"><input type="checkbox" name="is_employee" value="1" @checked($thirdParty->is_employee)> EMPLEADO</label>
                                                </div>
                                                <select name="active" class="rounded border-gray-300 text-sm">
                                                    <option value="1" @selected($thirdParty->active)>Activo</option>
                                                    <option value="0" @selected(! $thirdParty->active)>Inactivo</option>
                                                </select>
                                                <button class="rounded bg-blue-600 px-3 py-2 text-sm font-bold text-white">Guardar cambios</button>
                                            </form>
                                            <form data-rut-upload class="mt-4 space-y-2 rounded-lg border border-dashed border-gray-300 p-3">
                                                @csrf
                                                <label class="block text-xs font-semibold text-gray-700">Autocompletar desde PDF del RUT</label>
                                                <input type="file" name="rut_pdf" accept="application/pdf,.pdf" required class="w-full text-xs">
                                                <button type="button" data-rut-extract data-url="{{ route('third-parties.rut.extract') }}" class="w-full rounded border border-[#227c70] px-3 py-2 text-xs font-bold text-[#227c70]">Leer PDF y completar datos</button>
                                            </form>
                                            <p data-rut-feedback class="mt-2 hidden text-xs" role="status" aria-live="polite"></p>
                                        </div>
                                    </div>
                                </dialog>
                            @empty
                                <tr><td colspan="8" class="p-3">No hay terceros registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-rut-extract]');

            if (!button) {
                return;
            }

            const editor = button.closest('[data-third-party-editor]');
            const uploadForm = editor.querySelector('[data-rut-upload]');
            const fileInput = uploadForm.querySelector('input[type="file"]');
            const feedback = editor.querySelector('[data-rut-feedback]');
            const file = fileInput.files[0];

            if (!file) {
                feedback.textContent = 'Selecciona el archivo PDF del RUT.';
                feedback.className = 'mt-2 text-xs text-red-700';
                return;
            }

            const formData = new FormData();
            formData.append('rut_pdf', file);
            formData.append('_token', uploadForm.querySelector('input[name="_token"]').value);
            button.disabled = true;
            button.textContent = 'Leyendo RUT…';
            feedback.textContent = '';

            try {
                const response = await fetch(button.dataset.url, {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: formData,
                });
                const result = await response.json();

                if (!response.ok) {
                    const validationMessage = Object.values(result.errors || {}).flat()[0];
                    throw new Error(validationMessage || result.message || 'No se pudo leer el PDF.');
                }

                const dataForm = editor.querySelector('form[method="POST"]:not([data-rut-upload])');

                Object.entries(result.data).forEach(([name, value]) => {
                    const input = dataForm.querySelector(`[name="${name}"]`);

                    if (input && value) {
                        input.value = value;
                    }
                });

                feedback.textContent = 'Datos extraídos. Revísalos antes de guardar.';
                feedback.className = 'mt-2 text-xs text-emerald-700';
            } catch (error) {
                feedback.textContent = error instanceof Error ? error.message : 'Ocurrió un error al leer el PDF.';
                feedback.className = 'mt-2 text-xs text-red-700';
            } finally {
                button.disabled = false;
                button.textContent = 'Leer PDF y completar datos';
            }
        });
    </script>
</x-app-layout>
