<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-[#d96b4c]">SU+ GESTION EMPRESARIA / Centro de control</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-[#192522]">Tablero principal</h2>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label class="relative block w-full sm:w-80">
                    <span class="sr-only">Buscar en SU+ GESTION EMPRESARIA</span>
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[#71807a]">⌕</span>
                    <input id="module-search" type="search" placeholder="Buscar módulo o acción..." class="w-full rounded-xl border-[#d7dfd8] bg-[#f3f5f1] py-2.5 pl-9 pr-4 text-sm text-[#192522] placeholder:text-[#8b9992] focus:border-[#227c70] focus:ring-[#227c70]">
                </label>
                <button type="button" onclick="openDocumentLookup()" class="rounded-xl border border-[#d7dfd8] bg-white px-4 py-2.5 text-sm font-bold text-[#227c70]">Buscar comprobante</button>
                @guest
                    <div class="flex gap-2 text-sm"><a href="{{ route('login') }}" class="rounded-lg bg-[#192522] px-4 py-2 font-bold text-white">Ingresar</a><a href="{{ route('register') }}" class="rounded-lg border border-[#d7dfd8] bg-white px-4 py-2 font-bold text-[#227c70]">Registrarse</a></div>
                @else
                    <form method="POST" action="{{ route('company.switch') }}" class="flex items-center gap-2 rounded-xl border border-[#d7dfd8] bg-white px-3 py-2">
                        @csrf
                        <label for="active-company" class="text-[10px] font-bold uppercase tracking-wider text-[#71807a]">Empresa</label>
                        <select id="active-company" name="company_id" onchange="this.form.submit()" class="border-0 bg-transparent py-0 pl-0 pr-6 text-sm font-bold text-[#227c70] focus:ring-0">
                            @foreach($companies as $company)<option value="{{ $company->id }}" @selected((int) session('company_id') === $company->id)>{{ $company->name }}</option>@endforeach
                        </select>
                    </form>
                @endguest
            </div>
        </div>
    </x-slot>

    <div class="min-h-[calc(100vh-65px)] bg-[#f3f5f1] px-4 py-6 sm:px-8">
        <div class="mx-auto max-w-7xl">
            <section class="relative overflow-hidden rounded-2xl bg-[#227c70] px-6 py-8 text-white shadow-lg shadow-[#227c70]/15 sm:px-10">
                <div class="relative z-10 max-w-2xl">
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-[#b8e3d7]">Centro de operaciones</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">¿Qué necesitas hacer hoy?</h1>
                    <p class="mt-3 text-sm leading-6 text-[#d8f0e8]">Busca una función o elige un botón para entrar directamente a tu flujo de trabajo.</p>
                </div>
                <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full border-[24px] border-white/10"></div>
            </section>

            @php
                $modules = [
                    ['name' => 'Compras', 'hint' => 'Facturas y XML', 'route' => 'purchases.index', 'icon' => '▣', 'tone' => 'bg-[#fff0e8] text-[#b65338]'],
                    ['name' => 'Ventas', 'hint' => 'Facturación', 'route' => 'sales.index', 'icon' => '↗', 'tone' => 'bg-[#edf7f4] text-[#227c70]'],
                    ['name' => 'POS electrónico', 'hint' => 'Punto de venta rápido', 'route' => 'pos.index', 'icon' => '⊡', 'tone' => 'bg-[#edf7f4] text-[#227c70]'],
                    ['name' => 'Inventario', 'hint' => 'Productos y stock', 'route' => 'items.index', 'icon' => '□', 'tone' => 'bg-[#eef1fb] text-[#5064a4]'],
                    ['name' => 'Nómina', 'hint' => 'Liquidación', 'route' => 'payroll.index', 'icon' => '◎', 'tone' => 'bg-[#f4f1fb] text-[#73559e]'],
                    ['name' => 'Bancos', 'hint' => 'Conciliación', 'route' => 'bank-reconciliation.index', 'icon' => '⌁', 'tone' => 'bg-[#eaf5f8] text-[#14728a]'],
                    ['name' => 'Logística', 'hint' => 'Rutas y entregas', 'route' => 'logistics.index', 'icon' => '⌖', 'tone' => 'bg-[#fff8df] text-[#a67b16]'],
                    ['name' => 'Contabilidad', 'hint' => 'Comprobantes', 'route' => 'accounting.vouchers.index', 'icon' => '◇', 'tone' => 'bg-[#f1f4f2] text-[#354d45]'],
                    ['name' => 'PUC', 'hint' => 'Cuentas auxiliares', 'route' => 'accounting.puc.index', 'icon' => '⊙', 'tone' => 'bg-[#edf7f4] text-[#227c70]'],
                    ['name' => 'Terceros', 'hint' => 'Clientes y proveedores', 'route' => 'third-parties.index', 'icon' => '♙', 'tone' => 'bg-[#fff0e8] text-[#b65338]'],
                    ['name' => 'Nota crédito clientes', 'hint' => 'Devoluciones y ajustes', 'route' => 'commercial-documents.customer-credit-notes', 'icon' => '⤷', 'tone' => 'bg-[#edf7f4] text-[#227c70]'],
                    ['name' => 'Nota débito proveedores', 'hint' => 'Ajustes a proveedores', 'route' => 'commercial-documents.supplier-debit-notes', 'icon' => '⤶', 'tone' => 'bg-[#fff0e8] text-[#b65338]'],
                    ['name' => 'Reportes', 'hint' => 'Indicadores', 'route' => 'reports.index', 'icon' => '▥', 'tone' => 'bg-[#eef1fb] text-[#5064a4]'],
                    ['name' => 'Normativa', 'hint' => 'Consulta legal colombiana', 'route' => 'regulations.index', 'icon' => '§', 'tone' => 'bg-[#f1f4f2] text-[#354d45]'],
                    ['name' => 'Resoluciones DIAN', 'hint' => 'Numeración autorizada', 'route' => 'numbering-resolutions.index', 'icon' => '№', 'tone' => 'bg-[#fff8df] text-[#a67b16]'],
                    ['name' => 'Operaciones masivas', 'hint' => 'Imprimir y enviar', 'route' => 'bulk-operations.index', 'icon' => '⇪', 'tone' => 'bg-[#f4f1fb] text-[#73559e]'],
                    ['name' => 'Administración', 'hint' => 'Empresa y usuarios', 'route' => 'admin.index', 'icon' => '⚙', 'tone' => 'bg-[#f1f4f2] text-[#354d45]'],
                ];
            @endphp

            <div id="module-grid" class="mx-auto mt-6 grid max-w-5xl grid-cols-2 justify-items-center gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($modules as $module)
                    <a href="{{ route($module['route']) }}" data-module-card data-search="{{ strtolower($module['name'].' '.$module['hint']) }}" class="group flex min-h-36 w-full max-w-56 flex-col justify-between rounded-2xl border border-[#d7dfd8] bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#227c70] hover:shadow-lg sm:p-5">
                        <span class="grid h-11 w-11 place-items-center rounded-xl text-2xl font-black {{ $module['tone'] }}">{{ $module['icon'] }}</span>
                        <span><span class="mt-5 block text-sm font-black text-[#192522]">{{ $module['name'] }}</span><span class="mt-1 block text-xs text-[#71807a]">{{ $module['hint'] }}</span></span>
                    </a>
                @endforeach
            </div>
            <p id="empty-search" class="mt-8 hidden rounded-xl border border-dashed border-[#c7d2cb] bg-white p-8 text-center text-sm text-[#71807a]">No encontramos un módulo con esa búsqueda.</p>

            <section class="mx-auto mt-6 grid max-w-5xl gap-4 md:grid-cols-3">
                <a href="{{ route('items.create') }}" class="rounded-2xl bg-[#192522] p-5 text-white transition hover:bg-[#263b36]"><span class="text-2xl text-[#f5c96a]">＋</span><p class="mt-5 font-black">Crear producto</p><p class="mt-1 text-xs text-[#b9c9c1]">Añade una referencia al inventario.</p></a>
                <a href="{{ route('commercial-documents.quotations') }}" class="rounded-2xl bg-[#f5c96a] p-5 text-[#263b36] transition hover:bg-[#ffda89]"><span class="text-2xl">≡</span><p class="mt-5 font-black">Nueva cotización</p><p class="mt-1 text-xs text-[#52635b]">Convierte oportunidades en ventas.</p></a>
                <a href="{{ route('purchases.index') }}#dian" class="rounded-2xl bg-[#d96b4c] p-5 text-white transition hover:bg-[#bd583c]"><span class="text-2xl">⚡</span><p class="mt-5 font-black">Cargar XML DIAN</p><p class="mt-1 text-xs text-[#ffe4d8]">Automatiza la lectura de compras.</p></a>
            </section>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('module-search');
        const moduleCards = [...document.querySelectorAll('[data-module-card]')];
        const emptySearch = document.getElementById('empty-search');
        searchInput?.addEventListener('input', (event) => {
            const query = event.target.value.trim().toLowerCase();
            let visible = 0;
            moduleCards.forEach((card) => {
                const matches = !query || card.dataset.search.includes(query);
                card.classList.toggle('hidden', !matches);
                visible += matches ? 1 : 0;
            });
            emptySearch?.classList.toggle('hidden', visible > 0);
        });

        function openDocumentLookup() {
            document.getElementById('document-lookup-modal').classList.remove('hidden');
            document.getElementById('document-lookup-modal').classList.add('flex');
        }

        function closeDocumentLookup() {
            document.getElementById('document-lookup-modal').classList.add('hidden');
            document.getElementById('document-lookup-modal').classList.remove('flex');
            document.getElementById('document-lookup-error').classList.add('hidden');
        }

        async function submitDocumentLookup(event) {
            event.preventDefault();
            const module = document.getElementById('document-lookup-module').value;
            const consecutive = document.getElementById('document-lookup-consecutive').value.trim();
            const errorBox = document.getElementById('document-lookup-error');
            errorBox.classList.add('hidden');
            if (!consecutive) {
                return;
            }
            const response = await fetch(`{{ route('documents.lookup') }}?module=${encodeURIComponent(module)}&consecutive=${encodeURIComponent(consecutive)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (!response.ok || !data.found) {
                errorBox.textContent = data.message || 'No se encontró el comprobante.';
                errorBox.classList.remove('hidden');
                return;
            }
            closeDocumentLookup();
            openVoucherModal(data.voucher_id);
        }
    </script>
    <div id="document-lookup-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50" onclick="if(event.target === this) closeDocumentLookup()">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between"><h3 class="text-lg font-black text-[#192522]">Buscar comprobante</h3><button type="button" onclick="closeDocumentLookup()" class="text-[#71807a]">✕</button></div>
            <form onsubmit="submitDocumentLookup(event)" class="mt-4 space-y-3">
                <select id="document-lookup-module" class="w-full rounded-xl border-[#d7dfd8] text-sm">
                    @foreach(\App\Http\Controllers\DocumentLookupController::MODULES as $key => $module)
                        <option value="{{ $key }}">{{ $module['label'] }}</option>
                    @endforeach
                </select>
                <input id="document-lookup-consecutive" type="text" required placeholder="Consecutivo (ej. 1, FAC-001)" class="w-full rounded-xl border-[#d7dfd8] text-sm">
                <p id="document-lookup-error" class="hidden rounded-lg bg-red-50 p-2 text-xs text-red-600"></p>
                <button type="submit" class="w-full rounded-xl bg-[#227c70] px-4 py-2.5 text-sm font-bold text-white">Buscar</button>
            </form>
        </div>
    </div>
    <x-voucher-modal />
</x-app-layout>
