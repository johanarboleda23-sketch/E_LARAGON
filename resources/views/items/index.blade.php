<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-pink-700 leading-tight">
                {{ __('Módulo de Inventario (Productos y Servicios)') }}
            </h2>
            <div class="space-x-2">
                <!-- Opción 1: Ir a la página completa (Rosa Suave) -->
                <a href="{{ route('items.create') }}" class="inline-flex items-center px-4 py-2 bg-pink-100 border border-pink-300 rounded-md font-semibold text-xs text-pink-700 uppercase tracking-widest hover:bg-pink-200 active:bg-pink-300 focus:outline-none focus:ring ring-pink-200 transition ease-in-out duration-150">
                    + Crear en Página Nueva
                </a>
                <!-- Opción 2: Abrir Ventana Flotante Modal (Rosa Claro Destacado) -->
                <button onclick="document.getElementById('modal-crear').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 bg-pink-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pink-600 active:bg-pink-700 focus:outline-none focus:ring ring-pink-200 transition ease-in-out duration-150 shadow-sm">
                    + Crear en Modal Flotante
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-t-4 border-pink-400">
                
                @if (session('success'))
                    <div class="mb-4 p-4 bg-pink-50 text-pink-700 rounded-md border border-pink-200">
                        {{ session('success') }}
                    </div>
                @endif

                <!-- Tabla de Registros -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-pink-100">
                        <thead class="bg-pink-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Código</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">P. Venta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">P. Costo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr class="hover:bg-pink-50/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $item->type === 'producto' ? 'bg-purple-100 text-purple-800' : 'bg-rose-100 text-rose-800' }}">
                                            {{ ucfirst($item->type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">{{ $item->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->code ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($item->sale_price, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->purchase_price ? '$'.number_format($item->purchase_price, 2) : '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $item->type === 'producto' ? $item->stock : 'Ilimitado (Servicio)' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($item->type === 'producto' && $item->stock <= $item->min_stock)
                                            <span class="text-rose-600 font-bold text-xs bg-rose-50 px-2 py-1 rounded">Stock Bajo</span>
                                        @else
                                            <span class="text-emerald-600 font-semibold text-xs bg-emerald-50 px-2 py-1 rounded">Activo</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-400">No hay productos ni servicios registrados aún.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL FLOTANTE ESTILIZADO EN ROSA CLARO -->
    <div id="modal-crear" class="fixed inset-0 bg-gray-600 bg-opacity-40 overflow-y-auto h-full w-full hidden z-50 transition-all">
        <div class="relative top-20 mx-auto p-6 border w-96 shadow-xl rounded-lg bg-white border-t-4 border-pink-400">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-pink-700">Registrar en Modal</h3>
                <button onclick="document.getElementById('modal-crear').classList.add('hidden')" class="text-gray-400 hover:text-pink-600 text-2xl font-bold transition-colors">&times;</button>
            </div>
            
            <form action="{{ route('items.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Tipo de Registro</label>
                    <select name="type" required class="mt-1 block w-full rounded-md border-pink-200 shadow-sm focus:border-pink-500 focus:ring focus:ring-pink-200 focus:ring-opacity-50 sm:text-sm">
                        <option value="producto">Producto Físico</option>
                        <option value="servicio">Servicio</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Nombre / Título</label>
                    <input type="text" name="name" required class="mt-1 block w-full rounded-md border-pink-200 shadow-sm focus:border-pink-500 focus:ring focus:ring-pink-200 focus:ring-opacity-50 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Código / SKU (Opcional)</label>
                    <input type="text" name="code" class="mt-1 block w-full rounded-md border-pink-200 shadow-sm focus:border-pink-500 focus:ring focus:ring-pink-200 focus:ring-opacity-50 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Precio de Venta</label>
                    <input type="number" step="0.01" name="sale_price" required class="mt-1 block w-full rounded-md border-pink-200 shadow-sm focus:border-pink-500 focus:ring focus:ring-pink-200 focus:ring-opacity-50 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Costo de Compra (Productos)</label>
                    <input type="number" step="0.01" name="purchase_price" class="mt-1 block w-full rounded-md border-pink-200 shadow-sm focus:border-pink-500 focus:ring focus:ring-pink-200 focus:ring-opacity-50 sm:text-sm">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Stock Inicial</label>
                        <input type="number" name="stock" value="0" class="mt-1 block w-full rounded-md border-pink-200 shadow-sm focus:border-pink-500 focus:ring focus:ring-pink-200 focus:ring-opacity-50 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Stock Mínimo</label>
                        <input type="number" name="min_stock" value="0" class="mt-1 block w-full rounded-md border-pink-200 shadow-sm focus:border-pink-500 focus:ring focus:ring-pink-200 focus:ring-opacity-50 sm:text-sm">
                    </div>
                </div>
                <div class="flex justify-end space-x-2 pt-3 border-t border-pink-50">
                    <button type="button" onclick="document.getElementById('modal-crear').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-md text-sm font-medium hover:bg-gray-200 transition-colors">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-pink-500 text-white rounded-md text-sm font-medium hover:bg-pink-600 transition-colors shadow-sm">Guardar Registro</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>