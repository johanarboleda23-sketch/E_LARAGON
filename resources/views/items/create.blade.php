<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Registrar Nuevo Item (Página Completa)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl bg-white p-6 shadow-sm sm:p-8">
                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form action="{{ route('items.store') }}" method="POST" class="space-y-5">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo de Registro</label>
                        <select id="item-type" name="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="producto" @selected(old('type', 'producto') === 'producto')>Producto Físico</option>
                            <option value="servicio">Servicio</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre / Título</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Código / SKU (Opcional)</label>
                        <input type="text" name="code" value="{{ old('code') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                        <label class="block text-sm font-medium text-gray-700">Precio de Venta</label>
                        <input type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                        <label class="block text-sm font-medium text-gray-700">Costo de Compra (Solo Productos)</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    <fieldset id="unit-fields" class="space-y-4 rounded-xl border border-gray-200 p-4">
                        <legend class="px-2 text-sm font-bold text-gray-800">Unidades y presentaciones</legend>
                        <p class="text-xs text-gray-500">El inventario se controla en la unidad base. Cada presentación indica cuántas unidades base contiene.</p>
                        <label class="block text-sm font-medium text-gray-700">Unidad base
                            <input name="base_unit" list="common-units" value="{{ old('base_unit', 'unidad') }}" maxlength="40" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <datalist id="common-units">@foreach($unitOptions as $unit)<option value="{{ $unit }}">@endforeach</datalist>
                        <div id="unit-conversions" class="space-y-3">
                            @foreach(old('unit_conversions', []) as $index => $conversion)
                                <div class="unit-conversion-row grid grid-cols-1 items-end gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
                                    <label class="text-xs font-semibold text-gray-600">Presentación<input name="unit_conversions[{{ $index }}][name]" value="{{ $conversion['name'] }}" type="text" maxlength="80" required class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
                                    <label class="text-xs font-semibold text-gray-600">Equivale a<input name="unit_conversions[{{ $index }}][factor]" value="{{ $conversion['factor'] }}" type="number" min="0.000001" step="0.000001" required class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
                                    <label class="text-xs font-semibold text-gray-600">Precio de venta propio (opcional)<input name="unit_conversions[{{ $index }}][sale_price]" value="{{ $conversion['sale_price'] ?? '' }}" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
                                    <button type="button" class="remove-unit-conversion rounded-md px-3 py-2 text-sm font-bold text-red-600">Quitar</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="add-unit-conversion" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-teal-700">＋ Agregar presentación</button>
                    </fieldset>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock Inicial</label>
                            <input type="number" name="stock" step="0.0001" min="0" value="{{ old('stock', '0') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock Mínimo</label>
                            <input type="number" name="min_stock" step="0.0001" min="0" value="{{ old('min_stock', '0') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-4">
                        <a href="{{ route('items.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm font-medium">Volver</a>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700 text-sm font-medium">Guardar Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <template id="unit-conversion-template">
        <div class="unit-conversion-row grid grid-cols-1 items-end gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <label class="text-xs font-semibold text-gray-600">Presentación<input data-field="name" type="text" maxlength="80" required class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-600">Equivale a<input data-field="factor" type="number" min="0.000001" step="0.000001" required class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-600">Precio de venta propio (opcional)<input data-field="sale_price" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
            <button type="button" class="remove-unit-conversion rounded-md px-3 py-2 text-sm font-bold text-red-600">Quitar</button>
        </div>
    </template>
    <script>
        let unitConversionIndex = {{ count(old('unit_conversions', [])) }};
        const unitContainer = document.getElementById('unit-conversions');
        unitContainer.querySelectorAll('.remove-unit-conversion').forEach((button) => button.addEventListener('click', () => button.closest('.unit-conversion-row').remove()));
        function addUnitConversion(values = {}) {
            const fragment = document.getElementById('unit-conversion-template').content.cloneNode(true);
            const row = fragment.querySelector('.unit-conversion-row');
            row.querySelectorAll('[data-field]').forEach((input) => {
                const field = input.dataset.field;
                input.name = `unit_conversions[${unitConversionIndex}][${field}]`;
                input.value = values[field] ?? '';
            });
            row.querySelector('.remove-unit-conversion').addEventListener('click', () => row.remove());
            unitContainer.appendChild(fragment);
            unitConversionIndex++;
        }
        document.getElementById('add-unit-conversion').addEventListener('click', () => addUnitConversion());
        document.getElementById('item-type').addEventListener('change', (event) => {
            document.getElementById('unit-fields').disabled = event.target.value !== 'producto';
        });
        document.getElementById('unit-fields').disabled = document.getElementById('item-type').value !== 'producto';
    </script>
</x-app-layout>