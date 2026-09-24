<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <span class="text-xl">🛒</span>
                <h2 class="font-semibold text-lg text-pink-700 leading-tight">Módulo de Gestión de Compras Avanzado</h2>
            </div>
            <div class="flex space-x-2">
                <a href="/items" class="inline-flex items-center px-3 py-1.5 bg-pink-100 border border-pink-200 rounded-md font-bold text-xs text-pink-700 uppercase hover:bg-pink-200 transition shadow-sm">
                     Módulo Inventario
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 bg-pink-50/10 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form action="{{ route('purchases.store') }}" method="POST" id="purchase-form" class="bg-white shadow-xl rounded-xl p-8 border border-pink-100 space-y-6">
                @csrf
                
                <div class="bg-pink-50/30 p-6 rounded-xl border border-pink-100/70 grid grid-cols-1 md:grid-cols-5 gap-4 text-xs">
                    <div>
                        <label class="block font-bold text-pink-700 uppercase tracking-wider mb-1">Tipo Documento</label>
                        <select name="document_type_id" class="w-full rounded-lg border-pink-200 text-xs shadow-sm"><option value="contado">Factura Contado (FCC)</option><option value="credito">Factura Crédito (FCR)</option></select>
                    </div>
                    <div><label class="block font-bold text-pink-700 uppercase tracking-wider mb-1">Consecutivo</label><input type="text" name="consecutivo" value="COM-001" class="w-full rounded-lg border-pink-200 font-semibold text-gray-700"></div>
                    <div><label class="block font-bold text-pink-700 uppercase tracking-wider mb-1">N° Factura</label><input type="text" name="invoice_number" required class="w-full rounded-lg border-pink-200 shadow-sm"></div>
                    <div><label class="block font-bold text-pink-700 uppercase tracking-wider mb-1">Proveedor / NIT</label><input type="text" name="provider" required class="w-full rounded-lg border-pink-200 shadow-sm"></div>
                    <div>
                        <label class="block font-bold text-pink-700 uppercase tracking-wider mb-1">Responsabilidad Fiscal</label>
                        <select name="provider_regimen" id="provider-regimen" onchange="calculateTotals()" required class="w-full rounded-lg border-pink-200 text-xs shadow-sm"><option value="comun">Régimen Común</option><option value="simplificado">Régimen Simplificado</option></select>
                    </div>
                </div>

                <datalist id="products-list">
                    @foreach(app('App\Models\Item')->all() as $product)<option value="{{ $product->name }}" data-id="{{ $product->id }}"></option>@endforeach
                </datalist>
                <div class="overflow-hidden rounded-xl border border-pink-100 shadow-sm">
                    <table class="min-w-full divide-y divide-pink-100" id="items-table">
                        <thead class="bg-pink-500 text-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Producto o Referencia</th>
                                <th class="px-2 py-3 text-center text-xs font-bold w-20">Cant.</th>
                                <th class="px-4 py-3 text-right text-xs font-bold w-28">Costo (\$)</th>
                                <th class="px-4 py-3 text-center text-xs font-bold w-24">IVA</th>
                                <th class="px-4 py-3 text-center text-xs font-bold w-24">Ganancia</th>
                                <th class="px-4 py-3 text-right text-xs font-bold w-32">Total (\$)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-pink-50" id="table-body">
                            <tr class="item-row">
                                <td class="px-3 py-3"><input type="text" list="products-list" name="items[product_name]" placeholder="🔍 Escriba artículo..." required class="product-search w-full rounded-lg border-pink-200 text-xs shadow-sm px-3 py-2"><input type="hidden" name="items[item_id]" class="product-id"></td>
                                <td class="px-2 py-3"><input type="number" name="items[quantity]" class="qty-input w-full text-center rounded-lg border-pink-200 text-xs shadow-sm" value="1" min="1" required></td>
                                <td class="px-2 py-3"><input type="number" step="0.01" name="items[cost_price]" class="price-input w-full text-right rounded-lg border-pink-200 text-xs shadow-sm" placeholder="0.00" required></td>
                                <td class="px-2 py-3"><select name="items[iva_percentage]" class="iva-input w-full rounded-lg border-pink-200 text-xs text-center shadow-sm"><option value="19">19%</option><option value="0">0%</option></select></td>
                                <td class="px-2 py-3"><input type="number" name="items[utility_percentage]" class="utility-input w-full text-center rounded-lg border-pink-200 text-xs shadow-sm" value="30" required></td>
                                <td class="px-4 py-3 text-xs font-bold text-gray-800 text-right row-total">\$0.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-between items-center pt-2"><button type="button" onclick="addRow()" class="text-xs font-bold text-pink-600 bg-pink-50 px-4 py-2 rounded-lg border border-pink-200 shadow-sm">➕ Agregar Renglón</button></div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-pink-100">
                    <div class="md:col-span-2 space-y-4 bg-gray-50/50 p-6 rounded-xl border">
                        <h4 class="font-bold text-pink-700 text-xs mb-3 uppercase">Distribución Formas de Pago</h4>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>Efectivo (\$): <input type="number" class="w-full rounded-lg border-pink-200 p-2 shadow-sm"></div>
                            <div>Bancos (\$): <input type="number" class="w-full rounded-lg border-pink-200 p-2 shadow-sm"></div>
                        </div>
                    </div>
                    <div class="bg-pink-50/20 p-6 rounded-xl text-xs space-y-2 font-medium flex flex-col justify-between shadow-inner">
                        <div class="flex justify-between border-b pb-1.5"><span>Subtotal Bruto:</span><span id="invoice-subtotal">\$0.00</span></div>
                        <div class="flex justify-between border-b pb-1.5"><span>IVA General:</span><span id="invoice-iva">\$0.00</span></div>
                        <div class="flex justify-between text-pink-700 font-extrabold text-sm border-t pt-2.5"><span>Neto a Pagar:</span><span id="invoice-total" class="text-base">\$0.00</span></div>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-wrap gap-2 text-xs font-medium text-gray-600">
                    <button type="button" class="px-3 py-1.5 bg-white border rounded hover:bg-pink-50 transition">Editar</button>
                    <button type="button" class="px-3 py-1.5 bg-white border rounded hover:bg-red-50 transition">Eliminar</button>
                    <button type="button" class="px-3 py-1.5 bg-white border border-pink-200 text-pink-700 rounded hover:bg-pink-50 transition">Notas Crédito / Débito</button>
                    <button type="button" class="px-3 py-1.5 bg-white border border-blue-200 text-blue-700 rounded hover:bg-blue-50 transition">Estado Cartera (C x P)</button>
                    <button type="button" class="px-3 py-1.5 bg-white border border-purple-200 text-purple-700 rounded hover:bg-purple-50 transition">Ver Asiento Contable</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let rowCount = 1;
        document.getElementById('items-table').addEventListener('input', function(e) {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) { calculateTotals(); }
        });
        function addRow() {
            const tbody = document.getElementById('table-body'); const newRow = document.createElement('tr'); newRow.className = 'item-row hover:bg-pink-50/20 transition-colors';
            newRow.innerHTML = `
                <td class="px-3 py-3"><input type="text" list="products-list" name="items[\${rowCount}][product_name]" placeholder="🔍 Escriba artículo..." required class="product-search w-full rounded-lg border-pink-200 text-xs shadow-sm px-3 py-2"><input type="hidden" name="items[\${rowCount}][item_id]" class="product-id"></td>
                <td class="px-2 py-3"><input type="number" name="items[\${rowCount}][quantity]" class="qty-input w-full text-center rounded-lg border-pink-200 text-xs shadow-sm" value="1" min="1" required></td>
                <td class="px-2 py-3"><input type="number" step="0.01" name="items[\${rowCount}][cost_price]" class="price-input w-full text-right rounded-lg border-pink-200 text-xs shadow-sm" placeholder="0.00" required></td>
                <td class="px-2 py-3"><select name="items[\${rowCount}][iva_percentage]" class="iva-input w-full rounded-lg border-pink-200 text-xs text-center shadow-sm"><option value="19">19%</option><option value="0">0%</option></select></td>
                <td class="px-2 py-3"><input type="number" name="items[\${rowCount}][utility_percentage]" class="utility-input w-full text-center rounded-lg border-pink-200 text-xs shadow-sm" value="30" required></td>
                <td class="px-4 py-3 text-xs font-bold text-gray-800 text-right row-total">$0.00</td>
            `;
            tbody.appendChild(newRow); newRow.querySelector('.product-search').focus(); rowCount++;
        }
        function calculateTotals() {
            let s = 0, i = 0;
            document.querySelectorAll('.item-row').forEach(r => {
                const q = parseFloat(r.querySelector('.qty-input').value) || 0;
                const p = parseFloat(r.querySelector('.price-input').value) || 0;
                const ivaP = parseFloat(r.querySelector('.iva-input').value) || 0;
                const sub = q * p; s += sub; i += (sub * (ivaP / 100));
                r.querySelector('.row-total').innerText = '\$' + (sub + (sub * (ivaP / 100))).toFixed(2);
            });
            document.getElementById('invoice-subtotal').innerText = '\$' + s.toFixed(2);
            document.getElementById('invoice-iva').innerText = '\$' + i.toFixed(2);
            document.getElementById('invoice-total').innerText = '\$' + (s + i).toFixed(2);
        }
    </script>
</x-app-layout>