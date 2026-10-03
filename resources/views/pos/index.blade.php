<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-[#d96b4c]">SU+ GESTION EMPRESARIA / Punto de venta</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-[#192522]">POS electrónico</h2>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-xs font-bold text-[#227c70]">⌂ Tablero</a>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f3f5f1] px-4 py-6 sm:px-8">
        <div class="mx-auto max-w-6xl">
            @if(session('success'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-5 lg:grid-cols-3">
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm lg:col-span-2">
                    <h3 class="text-sm font-black text-[#192522]">Productos disponibles</h3>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach($productOptions as $product)
                            <button type="button" onclick="addToCart({{ $product['id'] }}, '{{ addslashes($product['name']) }}', {{ $product['price'] }}, {{ $product['stock'] }})" class="rounded-xl border border-[#d7dfd8] p-3 text-left transition hover:border-[#227c70] hover:bg-[#edf7f4]">
                                <span class="block text-sm font-bold text-[#192522]">{{ $product['name'] }}</span>
                                <span class="mt-1 block text-xs text-[#71807a]">${{ number_format($product['price'], 0) }} · Stock: {{ $product['stock'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-black text-[#192522]">Carrito</h3>
                    <input id="pos-customer" type="text" placeholder="Cliente (opcional, Consumidor final)" class="mt-3 w-full rounded-lg border-[#cbd6cf] text-sm">
                    <div id="pos-cart" class="mt-3 space-y-2 text-sm"></div>
                    <p id="pos-empty-cart" class="mt-3 text-xs text-[#71807a]">Agrega productos desde la lista.</p>
                    <div class="mt-4 border-t border-[#e6ede8] pt-3 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span id="pos-subtotal">$0</span></div>
                        <div class="flex justify-between"><span>IVA (19%)</span><span id="pos-iva">$0</span></div>
                        <div class="mt-1 flex justify-between text-base font-black text-[#192522]"><span>Total</span><span id="pos-total">$0</span></div>
                    </div>
                    <form id="pos-form" method="POST" action="{{ route('pos.store') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="customer_name" id="pos-customer-hidden">
                        <div id="pos-items-inputs"></div>
                        <button type="submit" class="w-full rounded-xl bg-[#227c70] px-4 py-2.5 text-sm font-bold text-white">Cobrar venta</button>
                    </form>
                </section>
            </div>

            <section class="mt-6 overflow-x-auto rounded-2xl border border-[#d7dfd8] bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-[#192522] text-xs uppercase text-white">
                        <tr><th class="p-3">Consecutivo</th><th class="p-3">Fecha</th><th class="p-3">Cliente</th><th class="p-3">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[#e6ede8]">
                        @forelse($sales as $sale)
                            <tr>
                                <td class="p-3 font-semibold text-[#192522]">{{ $sale->invoice_number }}</td>
                                <td class="p-3 text-[#364640]">{{ $sale->sale_date->format('d/m/Y') }}</td>
                                <td class="p-3 text-[#364640]">{{ $sale->customer_name }}</td>
                                <td class="p-3 text-[#364640]">${{ number_format($sale->total, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-6 text-center text-sm text-[#71807a]">Aún no hay ventas POS registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>

    <script>
        const cart = [];

        function formatMoney(value) {
            return '$' + Math.round(value).toLocaleString('es-CO');
        }

        function addToCart(id, name, price, stock) {
            const existing = cart.find((line) => line.id === id);
            if (existing) {
                if (existing.quantity < stock) {
                    existing.quantity++;
                }
            } else {
                cart.push({ id, name, price, stock, quantity: 1 });
            }
            renderCart();
        }

        function removeFromCart(id) {
            const index = cart.findIndex((line) => line.id === id);
            if (index !== -1) {
                cart.splice(index, 1);
            }
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('pos-cart');
            const empty = document.getElementById('pos-empty-cart');
            container.innerHTML = '';
            empty.classList.toggle('hidden', cart.length > 0);

            let subtotal = 0;
            cart.forEach((line) => {
                subtotal += line.price * line.quantity;
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between gap-2 rounded-lg bg-[#f3f5f1] px-3 py-2';
                row.innerHTML = `<span class="font-semibold text-[#192522]">${line.name} x${line.quantity}</span><span class="flex items-center gap-2"><span>${formatMoney(line.price * line.quantity)}</span><button type="button" class="text-xs font-bold text-red-600">✕</button></span>`;
                row.querySelector('button').addEventListener('click', () => removeFromCart(line.id));
                container.appendChild(row);
            });

            const iva = subtotal * 0.19;
            const total = subtotal + iva;
            document.getElementById('pos-subtotal').textContent = formatMoney(subtotal);
            document.getElementById('pos-iva').textContent = formatMoney(iva);
            document.getElementById('pos-total').textContent = formatMoney(total);
        }

        document.getElementById('pos-form').addEventListener('submit', (event) => {
            if (cart.length === 0) {
                event.preventDefault();
                alert('Agrega al menos un producto al carrito.');
                return;
            }
            document.getElementById('pos-customer-hidden').value = document.getElementById('pos-customer').value;
            const inputsContainer = document.getElementById('pos-items-inputs');
            inputsContainer.innerHTML = '';
            cart.forEach((line, index) => {
                inputsContainer.insertAdjacentHTML('beforeend', `<input type="hidden" name="items[${index}][item_id]" value="${line.id}"><input type="hidden" name="items[${index}][quantity]" value="${line.quantity}">`);
            });
        });
    </script>
</x-app-layout>
