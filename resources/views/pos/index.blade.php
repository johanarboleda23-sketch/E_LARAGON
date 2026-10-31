<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-[#d96b4c]">SU+ GESTION EMPRESARIAL / Punto de venta</p>
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
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach($productOptions as $product)
                            <div class="rounded-xl border border-[#d7dfd8] p-3">
                                <span class="block text-sm font-bold text-[#192522]">{{ $product['name'] }}</span>
                                <span class="mt-1 block text-xs text-[#71807a]">Existencia: {{ $product['stock'] }} {{ $product['base_unit'] }}</span>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" class="add-product rounded-lg bg-[#edf7f4] px-3 py-2 text-xs font-bold text-[#227c70]" data-item-id="{{ $product['id'] }}" data-name="{{ $product['name'] }}" data-price="{{ $product['price'] }}" data-stock="{{ $product['stock'] }}" data-unit-id="" data-unit-name="{{ $product['base_unit'] }}" data-factor="1">
                                        {{ $product['base_unit'] }} · ${{ number_format($product['price'], 0) }}
                                    </button>
                                    @foreach($product['units'] as $unit)
                                        @php($presentationPrice = $unit['sale_price'] ?? ($product['price'] * $unit['factor']))
                                        <button type="button" class="add-product rounded-lg border border-[#d7dfd8] px-3 py-2 text-xs font-semibold text-[#364640] hover:border-[#227c70]" data-item-id="{{ $product['id'] }}" data-name="{{ $product['name'] }}" data-price="{{ $presentationPrice }}" data-stock="{{ $product['stock'] }}" data-unit-id="{{ $unit['id'] }}" data-unit-name="{{ $unit['name'] }}" data-factor="{{ $unit['factor'] }}">
                                            {{ $unit['name'] }} × {{ $unit['factor'] }} · ${{ number_format($presentationPrice, 0) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
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

        function addToCart(id, name, price, stock, unitId, unitName, factor) {
            const key = `${id}:${unitId || 'base'}`;
            const availableQuantity = Number(stock) / Number(factor);
            const existing = cart.find((line) => line.key === key);
            if (existing) {
                if (existing.quantity + 1 <= availableQuantity) {
                    existing.quantity++;
                }
            } else {
                if (availableQuantity <= 0) {
                    alert('No hay existencias suficientes para esa presentación.');
                    return;
                }
                cart.push({ key, id, name, price, stock: availableQuantity, quantity: Math.min(1, availableQuantity), unitId, unitName, factor });
            }
            renderCart();
        }

        function removeFromCart(key) {
            const index = cart.findIndex((line) => line.key === key);
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

            cart.forEach((line) => {
                const row = document.createElement('div');
                row.className = 'flex flex-wrap items-center justify-between gap-2 rounded-lg bg-[#f3f5f1] px-3 py-2';
                const label = document.createElement('span');
                label.className = 'min-w-0 flex-1 font-semibold text-[#192522]';
                label.textContent = `${line.name} · ${line.unitName} × ${line.quantity}`;
                const quantityInput = document.createElement('input');
                quantityInput.type = 'number';
                quantityInput.min = '0.0001';
                quantityInput.step = '0.0001';
                quantityInput.max = String(line.stock);
                quantityInput.value = String(line.quantity);
                quantityInput.required = true;
                quantityInput.className = 'w-20 rounded border-[#cbd6cf] text-xs';
                quantityInput.setAttribute('aria-label', `Cantidad de ${line.name}`);
                quantityInput.addEventListener('input', () => {
                    const quantity = Number(quantityInput.value);
                    if (quantityInput.value !== '' && Number.isFinite(quantity) && quantity > 0 && quantity <= line.stock) {
                        line.quantity = quantity;
                        label.textContent = `${line.name} · ${line.unitName} × ${line.quantity}`;
                        total.textContent = formatMoney(line.price * line.quantity);
                        updateCartTotals();
                    }
                });
                const total = document.createElement('span');
                total.textContent = formatMoney(line.price * line.quantity);
                quantityInput.addEventListener('change', () => {
                    const quantity = Number(quantityInput.value);
                    if (quantityInput.value === '' || !Number.isFinite(quantity) || quantity <= 0 || quantity > line.stock) {
                        quantityInput.value = String(line.quantity);
                    }
                });
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'text-xs font-bold text-red-600';
                removeButton.textContent = '✕';
                removeButton.addEventListener('click', () => removeFromCart(line.key));
                row.append(label, quantityInput, total, removeButton);
                container.appendChild(row);
            });

            updateCartTotals();
        }

        function updateCartTotals() {
            let subtotal = 0;
            cart.forEach((line) => {
                subtotal += line.price * line.quantity;
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
                inputsContainer.insertAdjacentHTML('beforeend', `<input type="hidden" name="items[${index}][item_id]" value="${line.id}"><input type="hidden" name="items[${index}][quantity]" value="${line.quantity}"><input type="hidden" name="items[${index}][unit_conversion_id]" value="${line.unitId || ''}">`);
            });
        });
        document.querySelectorAll('.add-product').forEach((button) => {
            button.addEventListener('click', () => addToCart(
                Number(button.dataset.itemId),
                button.dataset.name,
                Number(button.dataset.price),
                Number(button.dataset.stock),
                button.dataset.unitId || null,
                button.dataset.unitName,
                Number(button.dataset.factor),
            ));
        });
    </script>
</x-app-layout>
