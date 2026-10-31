<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar registro de inventario</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl bg-white p-6 shadow-sm sm:p-8">
                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form action="{{ route('items.update', $item) }}" method="POST" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select id="item-type" name="type" required class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="producto" @selected(old('type', $item->type) === 'producto')>Producto</option>
                            <option value="servicio" @selected(old('type', $item->type) === 'servicio')>Servicio</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" name="name" value="{{ old('name', $item->name) }}" required class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Código / SKU</label>
                        <input type="text" name="code" value="{{ old('code', $item->code) }}" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Precio de venta</label>
                            <input type="number" name="sale_price" step="0.01" min="0" value="{{ old('sale_price', $item->sale_price) }}" required class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Costo de compra</label>
                            <input type="number" name="purchase_price" step="0.01" min="0" value="{{ old('purchase_price', $item->purchase_price) }}" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                    </div>
                    <fieldset id="unit-fields" class="space-y-4 rounded-xl border border-gray-200 p-4">
                        <legend class="px-2 text-sm font-bold text-gray-800">Unidades y presentaciones</legend>
                        <p class="text-xs text-gray-500">El inventario se controla en la unidad base. Cada presentación indica cuántas unidades base contiene.</p>
                        <label class="block text-sm font-medium text-gray-700">Unidad base
                            <input name="base_unit" list="common-units" value="{{ old('base_unit', $item->base_unit ?? 'unidad') }}" maxlength="40" required class="mt-1 block w-full rounded-md border-gray-300">
                        </label>
                        <datalist id="common-units">@foreach($unitOptions as $unit)<option value="{{ $unit }}">@endforeach</datalist>
                        <div id="unit-conversions" class="space-y-3">
                            @php($formConversions = old('unit_conversions', $item->unitConversions->map(fn ($conversion) => ['name' => $conversion->name, 'factor' => $conversion->factor, 'sale_price' => $conversion->sale_price])->all()))
                            @foreach($formConversions as $index => $conversion)
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
                            <label class="block text-sm font-medium text-gray-700">Stock</label>
                            <input type="number" name="stock" min="0" step="0.0001" value="{{ old('stock', $item->stock) }}" required class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock mínimo</label>
                            <input type="number" name="min_stock" min="0" step="0.0001" value="{{ old('min_stock', $item->min_stock) }}" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-4">
                        <a href="{{ route('items.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md text-sm">Cancelar</a>
                        <button type="submit" class="px-4 py-2 bg-pink-500 text-white rounded-md text-sm">Guardar cambios</button>
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
        let unitConversionIndex = {{ count($formConversions) }};
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