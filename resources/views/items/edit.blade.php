<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar registro de inventario</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('items.update', $item) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select name="type" required class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="producto" @selected($item->type === 'producto')>Producto</option>
                            <option value="servicio" @selected($item->type === 'servicio')>Servicio</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" name="name" value="{{ $item->name }}" required class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Código / SKU</label>
                        <input type="text" name="code" value="{{ $item->code }}" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Precio de venta</label>
                            <input type="number" name="sale_price" step="0.01" min="0" value="{{ $item->sale_price }}" required class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Costo de compra</label>
                            <input type="number" name="purchase_price" step="0.01" min="0" value="{{ $item->purchase_price }}" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock</label>
                            <input type="number" name="stock" min="0" value="{{ $item->stock }}" required class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock mínimo</label>
                            <input type="number" name="min_stock" min="0" value="{{ $item->min_stock }}" class="mt-1 block w-full rounded-md border-gray-300">
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
</x-app-layout>