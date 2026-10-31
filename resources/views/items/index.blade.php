<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-pink-700 leading-tight">{{ __('Inventario') }}</h2>
            <div class="space-x-2">
                <a href="{{ route('costs.index') }}" class="inline-flex items-center px-4 py-2 bg-teal-100 border border-teal-300 rounded-md font-semibold text-xs text-teal-700 uppercase tracking-widest hover:bg-teal-200">
                    💹 Costos y márgenes
                </a>
                <a href="{{ route('items.create') }}" class="inline-flex items-center px-4 py-2 bg-pink-100 border border-pink-300 rounded-md font-semibold text-xs text-pink-700 uppercase tracking-widest hover:bg-pink-200">
                    + Crear producto
                </a>
                <button onclick="document.getElementById('modal-crear').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 bg-pink-500 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pink-600 shadow-sm">
                    + Creación rápida
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-t-4 border-pink-400">
                <div class="mb-5 flex flex-col gap-4 rounded-lg border border-pink-100 bg-pink-50 p-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('items.template') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Descargar plantilla Excel
                        </a>
                        <a href="{{ route('items.export') }}" class="inline-flex items-center rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">
                            Exportar productos
                        </a>
                    </div>
                    <form method="POST" action="{{ route('items.import') }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        @csrf
                        <div>
                            <label for="products-file" class="mb-1 block text-sm font-medium text-gray-700">Importar productos (.xlsx o CSV)</label>
                            <input id="products-file" type="file" name="file" accept=".xlsx,.csv,.txt" required class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-pink-700">
                        </div>
                        <button type="submit" class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">
                            Importar
                        </button>
                    </form>
                </div>
                @if (session('success'))
                    <p class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</p>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form method="GET" action="{{ route('items.index') }}" class="mb-5 grid gap-3 md:grid-cols-4">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar nombre o código" class="rounded-md border-gray-300">
                    <select name="type" class="rounded-md border-gray-300">
                        <option value="">Todos los tipos</option>
                        <option value="producto" @selected(request('type') === 'producto')>Productos</option>
                        <option value="servicio" @selected(request('type') === 'servicio')>Servicios</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="low_stock" value="1" @checked(request('low_stock'))> Solo bajo mínimo</label>
                    <button class="rounded-md bg-pink-500 px-4 py-2 text-sm font-semibold text-white">Filtrar</button>
                </form>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-pink-100">
                        <thead class="bg-pink-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">P. Venta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">P. Compra</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Cuentas PUC</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-semibold text-pink-600">{{ ucfirst($item->type) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">${{ number_format($item->sale_price, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->purchase_price !== null ? '$' . number_format($item->purchase_price, 2) : 'N/A' }}</td>
                                    <td class="px-6 py-4 text-sm {{ $item->type === 'producto' && $item->stock <= $item->min_stock ? 'font-bold text-red-600' : 'text-gray-900' }}">
                                        @if ($item->type === 'producto')
                                            <div>{{ $item->stock }} {{ $item->base_unit ?? 'unidad' }} (mín. {{ $item->min_stock }})</div>
                                            @if ($item->unitConversions->isNotEmpty())
                                                <div class="mt-1 text-xs font-normal text-gray-500">
                                                    Presentaciones:
                                                    @foreach ($item->unitConversions as $conversion)
                                                        {{ $conversion->name }} (× {{ $conversion->factor }})@if (! $loop->last), @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        @else
                                            Servicio
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <button type="button" onclick="document.getElementById('modal-puc-{{ $item->id }}').classList.remove('hidden')" class="text-teal-700 hover:underline font-semibold">🔗 Amarrar a PUC</button>
                                        <p class="mt-1 text-gray-500">
                                            @if($item->type === 'producto')Inventario: {{ $item->inventoryAccount?->code ?? '—' }}<br>@endif
                                            Ingreso: {{ $item->incomeAccount?->code ?? '—' }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 text-sm"><a href="{{ route('items.edit', $item) }}" class="text-pink-600 hover:underline">Editar</a>
                                        @if ($item->type === 'producto')
                                            <form action="{{ route('items.stock', $item) }}" method="POST" class="mt-2 flex flex-wrap items-center gap-1">
                                                @csrf
                                                <select name="movement_type" class="rounded border-gray-300 text-xs">
                                                    <option value="entrada">Entrada</option>
                                                    <option value="salida">Salida</option>
                                                </select>
                                                <select name="unit_conversion_id" class="rounded border-gray-300 text-xs">
                                                    <option value="">{{ $item->base_unit ?? 'unidad' }}</option>
                                                    @foreach($item->unitConversions as $conversion)
                                                        <option value="{{ $conversion->id }}">{{ $conversion->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="number" name="quantity" min="0.0001" step="0.0001" value="1" required class="w-20 rounded border-gray-300 text-xs">
                                                <input type="text" name="reason" placeholder="Motivo" class="w-24 rounded border-gray-300 text-xs">
                                                <button class="text-blue-600 hover:underline">Aplicar</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('items.destroy', $item) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este registro?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ml-3 text-red-600 hover:underline">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-400">No hay registros aún.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALES: amarrar cada producto/servicio a sus cuentas PUC -->
    @foreach($items as $item)
        <div id="modal-puc-{{ $item->id }}" class="fixed inset-0 bg-gray-900 bg-opacity-40 hidden z-50 flex items-center justify-center">
            <div class="relative mx-auto p-6 border-2 border-teal-200 w-96 shadow-2xl rounded-xl bg-white border-t-8 border-t-teal-500">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-teal-700">Cuentas PUC de "{{ $item->name }}"</h3>
                    <button type="button" onclick="document.getElementById('modal-puc-{{ $item->id }}').classList.add('hidden')" class="text-gray-400 hover:text-teal-600 text-2xl font-bold">&times;</button>
                </div>
                <form method="POST" action="{{ route('items.accounts', $item) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    @if($item->type === 'producto')
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Cuenta de inventario (compras)</label>
                            <select name="inventory_account_id" class="mt-1 block w-full rounded-md border-teal-200 text-sm">
                                <option value="">Sin asociación</option>
                                @foreach($postingAccounts as $account)
                                    <option value="{{ $account->id }}" @selected($item->inventory_account_id === $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Se usa al comprar este producto. Si la dejas vacía, se usa la cuenta de inventario general (1435).</p>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Cuenta de ingreso (ventas)</label>
                        <select name="income_account_id" class="mt-1 block w-full rounded-md border-teal-200 text-sm">
                            <option value="">Sin asociación</option>
                            @foreach($postingAccounts as $account)
                                <option value="{{ $account->id }}" @selected($item->income_account_id === $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Se usa al vender este producto/servicio. Si la dejas vacía, se usa la cuenta de ingresos general (4135).</p>
                    </div>
                    <div class="flex justify-end space-x-2 pt-3 border-t border-teal-50">
                        <button type="button" onclick="document.getElementById('modal-puc-{{ $item->id }}').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-md text-sm font-medium">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-md text-sm font-medium hover:bg-teal-700 shadow-sm">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <!-- VENTANA FLOTANTE MODAL (Blanca con bordes rosa) -->
    <div id="modal-crear" class="fixed inset-0 hidden flex items-center justify-center overflow-y-auto bg-gray-900 bg-opacity-40 p-4 z-50">
        <div class="relative mx-auto max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl border-2 border-pink-200 border-t-8 border-t-pink-400 bg-white p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-pink-700">Registrar Item</h3>
                <button onclick="document.getElementById('modal-crear').classList.add('hidden')" class="text-gray-400 hover:text-pink-600 text-2xl font-bold">&times;</button>
            </div>
            <form action="{{ route('items.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Tipo</label>
                    <select id="quick-item-type" name="type" required class="mt-1 block w-full rounded-md border-pink-200">
                        <option value="producto">Producto</option>
                        <option value="servicio">Servicio</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Nombre</label>
                    <input type="text" name="name" required class="mt-1 block w-full rounded-md border-pink-200">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Precio Venta</label>
                    <input type="number" step="0.01" name="sale_price" required class="mt-1 block w-full rounded-md border-pink-200">
                </div>
                <fieldset id="quick-unit-fields" class="space-y-3 rounded-lg border border-pink-100 p-3">
                    <legend class="px-2 text-sm font-bold text-gray-700">Unidades, existencias y conversiones</legend>
                    <p class="text-xs text-gray-500">Las existencias se guardan en la unidad base. Cada presentación convierte su cantidad a esa unidad.</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="text-xs font-semibold text-gray-600">Unidad base
                            <input name="base_unit" list="quick-common-units" value="unidad" maxlength="40" required class="mt-1 block w-full rounded-md border-pink-200 text-sm">
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Stock inicial
                            <input type="number" name="stock" min="0" step="0.0001" value="0" class="mt-1 block w-full rounded-md border-pink-200 text-sm">
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Stock mínimo
                            <input type="number" name="min_stock" min="0" step="0.0001" value="0" class="mt-1 block w-full rounded-md border-pink-200 text-sm">
                        </label>
                    </div>
                    <datalist id="quick-common-units">@foreach($unitOptions as $unit)<option value="{{ $unit }}">@endforeach</datalist>
                    <div id="quick-unit-conversions" class="space-y-2"></div>
                    <button type="button" id="add-quick-unit-conversion" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-teal-700">＋ Agregar presentación y conversión</button>
                </fieldset>
                <div class="flex justify-end space-x-2 pt-3 border-t border-pink-50">
                    <button type="button" onclick="document.getElementById('modal-crear').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-md text-sm font-medium">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-pink-500 text-white rounded-md text-sm font-medium hover:bg-pink-600 shadow-sm">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <template id="quick-unit-conversion-template">
        <div class="grid grid-cols-1 items-end gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <label class="text-xs font-semibold text-gray-600">Presentación
                <input data-field="name" type="text" maxlength="80" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </label>
            <label class="text-xs font-semibold text-gray-600">Equivale a unidades base
                <input data-field="factor" type="number" min="0.000001" step="0.000001" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </label>
            <label class="text-xs font-semibold text-gray-600">Precio propio (opcional)
                <input data-field="sale_price" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </label>
            <button type="button" class="remove-quick-unit-conversion rounded-md px-3 py-2 text-sm font-bold text-red-600">Quitar</button>
        </div>
    </template>
    <script>
        const quickUnitFields = document.getElementById('quick-unit-fields');
        const quickItemType = document.getElementById('quick-item-type');
        const quickUnitConversions = document.getElementById('quick-unit-conversions');
        let quickUnitConversionIndex = 0;

        document.getElementById('add-quick-unit-conversion').addEventListener('click', () => {
            const fragment = document.getElementById('quick-unit-conversion-template').content.cloneNode(true);
            const row = fragment.querySelector('div');

            row.querySelectorAll('[data-field]').forEach((input) => {
                input.name = `unit_conversions[${quickUnitConversionIndex}][${input.dataset.field}]`;
            });
            row.querySelector('.remove-quick-unit-conversion').addEventListener('click', () => row.remove());
            quickUnitConversions.appendChild(fragment);
            quickUnitConversionIndex++;
        });
        quickItemType.addEventListener('change', () => {
            quickUnitFields.disabled = quickItemType.value !== 'producto';
        });
    </script>
</x-app-layout>