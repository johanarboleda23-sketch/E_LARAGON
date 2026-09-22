<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-pink-700 leading-tight">{{ __('Inventario') }}</h2>
            <div class="space-x-2">
                <a href="{{ route('items.create') }}" class="inline-flex items-center px-4 py-2 bg-pink-100 border border-pink-300 rounded-md font-semibold text-xs text-pink-700 uppercase tracking-widest hover:bg-pink-200">
                    + Página Nueva
                </a>
                <button onclick="document.getElementById('modal-crear').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 bg-pink-500 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pink-600 shadow-sm">
                    + Modal Flotante
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-t-4 border-pink-400">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-pink-100">
                        <thead class="bg-pink-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">P. Venta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Stock</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-semibold text-pink-600">{{ ucfirst($item->type) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">${{ number_format($item->sale_price, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->type === 'producto' ? $item->stock : 'Servicio' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-400">No hay registros aún.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- VENTANA FLOTANTE MODAL (Blanca con bordes rosa) -->
    <div id="modal-crear" class="fixed inset-0 bg-gray-900 bg-opacity-40 hidden z-50 flex items-center justify-center">
        <div class="relative mx-auto p-6 border-2 border-pink-200 w-96 shadow-2xl rounded-xl bg-white border-t-8 border-t-pink-400">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-pink-700">Registrar Item</h3>
                <button onclick="document.getElementById('modal-crear').classList.add('hidden')" class="text-gray-400 hover:text-pink-600 text-2xl font-bold">&times;</button>
            </div>
            <form action="{{ route('items.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Tipo</label>
                    <select name="type" required class="mt-1 block w-full rounded-md border-pink-200">
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
                <div class="flex justify-end space-x-2 pt-3 border-t border-pink-50">
                    <button type="button" onclick="document.getElementById('modal-crear').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-md text-sm font-medium">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-pink-500 text-white rounded-md text-sm font-medium hover:bg-pink-600 shadow-sm">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>