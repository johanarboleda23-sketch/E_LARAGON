<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-pink-700 leading-tight">Módulo de Compras e Impuestos</h2>
            <button onclick="document.getElementById('modal-compra').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 bg-pink-500 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pink-600 shadow-sm">
                + Registrar Factura de Compra
            </button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-t-4 border-pink-400">
                <h3 class="text-lg font-bold text-gray-700 mb-4">Historial de Facturas de Compra</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-pink-100">
                        <thead class="bg-pink-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Factura</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Proveedor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Subtotal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">IVA</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-pink-700 uppercase">Total a Pagar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse ($purchases as $purchase)
                                <tr class="hover:bg-pink-50/10 transition-colors">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">#{{ $purchase->invoice_number }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $purchase->provider }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">${{ number_format($purchase->subtotal, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">${{ number_format($purchase->iva_total, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-pink-700 font-bold">${{ number_format($purchase->total_pagar, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-400">No hay facturas ingresadas aún.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE COMPRA FLOTANTE -->
    <div id="modal-compra" class="fixed inset-0 bg-gray-900 bg-opacity-40 hidden z-50 flex items-center justify-center overflow-y-auto">
        <div class="relative mx-auto p-6 border-2 border-pink-200 w-full max-w-lg shadow-2xl rounded-xl bg-white border-t-8 border-t-pink-400 my-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-pink-700">Ingresar Factura de Proveedor</h3>
                <button type="button" onclick="document.getElementById('modal-compra').classList.add('hidden')" class="text-gray-400 hover:text-pink-600 text-2xl font-bold">&times;</button>
            </div>
            
            <form action="{{ route('purchases.store') }}" method="POST" class="space-y-4 text-sm text-gray-700">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-gray-700">N° Factura</label>
                        <input type="text" name="invoice_number" required class="mt-1 block w-full rounded-md border-pink-200">
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700">Proveedor / NIT</label>
                        <input type="text" name="provider" required class="mt-1 block w-full rounded-md border-pink-200">
                    </div>
                </div>

                <div class="border-t border-pink-100 pt-3">
                    <h4 class="font-bold text-pink-600 mb-2">Datos del Producto</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-gray-700">Seleccionar Item</label>
                            <select name="item_id" required class="mt-1 block w-full rounded-md border-pink-200">
                                <option value="">-- Seleccione --</option>
                                @foreach(\App\Models\Item::all() as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700">Cantidad Comprada</label>
                            <input type="number" name="quantity" id="compra-qty" value="1" min="1" oninput="calcularPrecios()" class="mt-1 block w-full rounded-md border-pink-200">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 bg-pink-50/50 p-3 rounded-lg border border-pink-100">
                    <div>
                        <label class="block text-xs font-bold text-gray-600">Costo Unitario ($)</label>
                        <input type="number" step="0.01" name="cost_price" id="compra-costo" oninput="calcularPrecios()" required class="mt-1 block w-full rounded-md border-pink-200 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600">Tarifa IVA (%)</label>
                        <select name="iva_percentage" id="compra-iva" onchange="calcularPrecios()" class="mt-1 block w-full rounded-md border-pink-200 text-xs">
                            <option value="19">19% General</option>
                            <option value="5">5% Canasta</option>
                            <option value="0">0% Exento</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600">Ganancia (%)</label>
                        <input type="number" name="utility_percentage" id="compra-utilidad" value="30" oninput="calcularPrecios()" required class="mt-1 block w-full rounded-md border-pink-200 text-xs">
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg space-y-2 border border-gray-200 font-medium">
                    <div class="flex justify-between text-gray-600"><span>Subtotal Factura:</span><span id="res-subtotal">$0.00</span></div>
                    <div class="flex justify-between text-gray-600"><span>IVA calculado:</span><span id="res-iva">$0.00</span></div>
                    <div class="flex justify-between text-blue-600 font-bold border-t border-gray-200 pt-1"><span>ReteFuente (2.5%):</span><span id="res-refuente">$0.00</span></div>
                    <div class="flex justify-between text-pink-700 font-extrabold text-base border-t border-pink-200 pt-2">
                        <span>Precio Venta Sugerido:</span>
                        <span id="res-ventasugerido">$0.00</span>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-pink-50">
                    <button type="button" onclick="document.getElementById('modal-compra').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-md">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-pink-500 text-white rounded-md hover:bg-pink-600 shadow-sm font-semibold transition-colors">Procesar Factura</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function calcularPrecios() {
            const qty = parseFloat(document.getElementById('compra-qty').value) || 0;
            const costo = parseFloat(document.getElementById('compra-costo').value) || 0;
            const ivaPorcentaje = parseFloat(document.getElementById('compra-iva').value) || 0;
            const utilidadPorcentaje = parseFloat(document.getElementById('compra-utilidad').value) || 0;

            const subtotal = costo * qty;
            const ivaTotal = subtotal * (ivaPorcentaje / 100);

            let retefuente = 0;
            if (subtotal >= 1272000) {
                retefuente = subtotal * 0.025;
            }

            const precioVentaUnitario = costo * (1 + (utilidadPorcentaje / 100));

            document.getElementById('res-subtotal').innerText = '$' + subtotal.toFixed(2);
            document.getElementById('res-iva').innerText = '$' + ivaTotal.toFixed(2);
            document.getElementById('res-refuente').innerText = '-$' + retefuente.toFixed(2);
            document.getElementById('res-ventasugerido').innerText = '$' + precioVentaUnitario.toFixed(2);
        }
    </script>
</x-app-layout>