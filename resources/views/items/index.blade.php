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
                                    <td class="px-6 py-4 text-sm {{ $item->type === 'producto' && $item->stock <= $item->min_stock ? 'font-bold text-red-600' : 'text-gray-900' }}">{{ $item->type === 'producto' ? $item->stock . ' (mín. ' . $item->min_stock . ')' : 'Servicio' }}</td>
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
                                                <input type="number" name="quantity" min="1" value="1" required class="w-16 rounded border-gray-300 text-xs">
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