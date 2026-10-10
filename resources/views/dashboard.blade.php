<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <!-- Título del Tablero -->
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-[#d96b4c]">SU+ GESTION EMPRESARIAL / Centro de control</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-[#192522]">Tablero principal</h2>
            </div>
            
            <!-- Contenedor de Acciones (Buscadores y Empresa) -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                
                <!-- 1. Buscador General de Módulos -->
                <label class="relative block w-full sm:w-64">
                    <span class="sr-only">Buscar en SU+ GESTION EMPRESARIAL</span>
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[#71807a]">⌕</span>
                    <input id="module-search" type="search" placeholder="Buscar módulo o acción..." class="w-full rounded-xl border-[#d7dfd8] bg-[#f3f5f1] py-2.5 pl-9 pr-4 text-sm text-[#192522] placeholder:text-[#8b9992] focus:border-[#227c70] focus:ring-[#227c70]">
                </label>

                <!-- 2. Buscador Comercial Directo (Consecutivos) -->
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center bg-[#f3f5f1] p-1.5 rounded-xl border border-[#d7dfd8]">
                    <input id="consecutivo_directo_dashboard" type="text" placeholder="Pegar consecutivo aquí..." class="w-full sm:w-40 rounded-lg border-0 bg-white py-1.5 px-3 text-sm text-[#192522] placeholder:text-[#8b9992] focus:ring-2 focus:ring-[#227c70]">
                    
                    <select id="tipo_comprobante_directo_dashboard" class="rounded-lg border-0 bg-white py-1.5 pl-3 pr-8 text-sm font-bold text-[#227c70] focus:ring-2 focus:ring-[#227c70]">
                        <option value="">Seleccione tipo...</option>
                        <option value="venta_factura">Venta (Factura)</option>
                        <option value="compra_factura">Compra (Factura)</option>
                        <option value="documento_soporte">Documento soporte</option>
                        <option value="nomina_periodo">Nómina (Periodo)</option>
                        <option value="comprobante_contable">Comprobante contable</option>
                    </select>

                    <button type="button" id="btn_buscar_comercial_dashboard" class="rounded-lg bg-[#227c70] px-4 py-1.5 text-sm font-bold text-white hover:bg-[#1a5f55] transition">Buscar</button>
                </div>
                <p id="mensaje_busqueda_comercial" class="hidden w-full text-xs font-bold sm:w-auto"></p>

                <!-- 3. Selector de Empresa Activa (Solo para usuarios autenticados) -->
                @auth
                    <form method="POST" action="{{ route('company.switch') }}" class="flex items-center gap-2 rounded-xl border border-[#d7dfd8] bg-white px-3 py-2">
                        @csrf
                        <label for="active-company" class="text-[10px] font-bold uppercase tracking-wider text-[#71807a]">Empresa</label>
                        <select id="active-company" name="company_id" onchange="this.form.submit()" class="border-0 bg-transparent py-0 pl-0 pr-6 text-sm font-bold text-[#227c70] focus:ring-0">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected((int) session('company_id') === $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endauth

            </div>
        </div>
    </x-slot>

    <!-- Cuerpo Principal del Dashboard -->
    <div class="min-h-[calc(100vh-65px)] bg-[#f3f5f1] px-4 py-6 sm:px-8">
        <div class="mx-auto max-w-7xl">
            <!-- Banner Principal -->
            <section class="relative overflow-hidden rounded-2xl bg-[#227c70] px-6 py-8 text-white shadow-lg shadow-[#227c70]/15 sm:px-10">
                <div class="relative z-10 max-w-2xl">
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-[#b8e3d7]">Centro de operaciones</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">¿Qué necesitas hacer hoy?</h1>
                    <p class="mt-3 text-sm leading-6 text-[#d8f0e8]">Busca una función o elige un botón para entrar directamente a tu flujo de trabajo.</p>
                </div>
                <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full border-[24px] border-white/10"></div>
            </section>

            <!-- Listado de Módulos del Sistema -->
            @php
                $modules = [
                    ['name' => 'Compras', 'hint' => 'Facturas y XML', 'route' => 'purchases.index', 'icon' => '📥'],
                    ['name' => 'Ventas', 'hint' => 'Facturación emitida', 'route' => 'sales.index', 'icon' => '📤'],
                    ['name' => 'Nómina', 'hint' => 'Gestión de personal', 'route' => 'payroll.index', 'icon' => '👥'],
                    ['name' => 'Contabilidad', 'hint' => 'Asientos y reportes', 'route' => 'accounting.vouchers.index', 'icon' => '📊'],
                    ['name' => 'PUC', 'hint' => 'Plan único de cuentas', 'route' => 'accounting.puc.index', 'icon' => '📒'],
                    ['name' => 'Documento soporte', 'hint' => 'Compras a no obligados', 'route' => 'support-documents.index', 'icon' => '🧾'],
                    ['name' => 'Conciliación bancaria', 'hint' => 'Extractos y cruces', 'route' => 'bank-reconciliation.index', 'icon' => '🏦'],
                    ['name' => 'Nota crédito clientes', 'hint' => 'Devoluciones a clientes', 'route' => 'commercial-documents.customer-credit-notes', 'icon' => '↩️'],
                    ['name' => 'Nota débito proveedores', 'hint' => 'Ajustes a proveedores', 'route' => 'commercial-documents.supplier-debit-notes', 'icon' => '↪️'],
                    ['name' => 'Logística', 'hint' => 'Rutas y entregas', 'route' => 'logistics.index', 'icon' => '🚚'],
                    ['name' => 'Reportes financieros', 'hint' => 'Excel y PDF', 'route' => 'reports.index', 'icon' => '📈'],
                    ['name' => 'Terceros', 'hint' => 'Clientes, proveedores, empleados', 'route' => 'third-parties.index', 'icon' => '🧑‍🤝‍🧑'],
                    ['name' => 'Inventario', 'hint' => 'Productos y stock', 'route' => 'items.index', 'icon' => '📦'],
                    ['name' => 'Indicadores financieros', 'hint' => 'Rentabilidad, cartera e inventario', 'route' => 'financial-dashboard.index', 'icon' => '📈'],
                    ['name' => 'Costos y márgenes', 'hint' => 'Costo promedio y precio sugerido', 'route' => 'costs.index', 'icon' => '💹'],
                    ['name' => 'Punto de venta', 'hint' => 'Ventas rápidas (POS)', 'route' => 'pos.index', 'icon' => '🛒'],
                    ['name' => 'Cotizaciones', 'hint' => 'Ofertas a clientes', 'route' => 'commercial-documents.quotations', 'icon' => '📝'],
                    ['name' => 'Órdenes de venta', 'hint' => 'Pedidos confirmados', 'route' => 'commercial-documents.sales-orders', 'icon' => '🗂️'],
                    ['name' => 'Remisiones', 'hint' => 'Entregas sin factura', 'route' => 'commercial-documents.remissions', 'icon' => '📦'],
                    ['name' => 'Órdenes de compra', 'hint' => 'Pedidos a proveedores', 'route' => 'commercial-documents.purchase-orders', 'icon' => '📋'],
                    ['name' => 'Arqueo de caja', 'hint' => 'Cuadre de caja general y menor', 'route' => 'cash-register.index', 'icon' => '🧾'],
                    ['name' => 'TRM y monedas', 'hint' => 'Tasas de cambio y conversor', 'route' => 'currency.index', 'icon' => '💱'],
                ];
            @endphp

            <div id="modules-grid" class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($modules as $module)
                    <a href="{{ route($module['route']) }}" data-module-name="{{ strtolower($module['name'].' '.$module['hint']) }}" class="flex items-center gap-4 rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="text-3xl">{{ $module['icon'] }}</div>
                        <div>
                            <h3 class="font-bold text-[#192522]">{{ $module['name'] }}</h3>
                            <p class="text-xs text-[#8b9992]">{{ $module['hint'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <p id="modules-empty" class="mt-6 hidden text-sm text-[#71807a]">Ningún módulo coincide con tu búsqueda.</p>
        </div>
    </div>

    <!-- Script de Búsqueda Sincronizado con el controlador real (DocumentLookupController) -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Buscador comercial directo (consecutivos) ---
        const inputConsecutivo = document.getElementById('consecutivo_directo_dashboard');
        const selectTipo = document.getElementById('tipo_comprobante_directo_dashboard');
        const btnBuscar = document.getElementById('btn_buscar_comercial_dashboard');
        const mensaje = document.getElementById('mensaje_busqueda_comercial');

        // El <select> usa claves en español; el backend (DocumentLookupController::MODULES)
        // espera estas otras claves.
        const mapaTipos = {
            venta_factura: 'sale',
            compra_factura: 'purchase',
            documento_soporte: 'support_document',
            nomina_periodo: 'payroll',
            comprobante_contable: 'voucher',
        };

        function mostrarMensaje(texto, tipo) {
            mensaje.textContent = texto;
            mensaje.classList.remove('hidden', 'text-[#b91c1c]', 'text-[#227c70]');
            mensaje.classList.add(tipo === 'error' ? 'text-[#b91c1c]' : 'text-[#227c70]');
        }

        function ejecutarBusqueda() {
            const consecutivo = inputConsecutivo.value.trim();
            const tipoSeleccionado = selectTipo.value;

            if (!consecutivo) {
                mostrarMensaje('Por favor, ingresa un consecutivo.', 'error');
                inputConsecutivo.focus();
                return;
            }

            if (!tipoSeleccionado) {
                mostrarMensaje('Por favor, selecciona un tipo de comprobante.', 'error');
                selectTipo.focus();
                return;
            }

            const modulo = mapaTipos[tipoSeleccionado];
            btnBuscar.disabled = true;
            btnBuscar.innerText = 'Buscando...';
            mensaje.classList.add('hidden');

            const url = new URL('{{ route('documents.lookup') }}', window.location.origin);
            url.searchParams.set('module', modulo);
            url.searchParams.set('consecutive', consecutivo);

            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
                .then(async (response) => ({ ok: response.ok, data: await response.json() }))
                .then(({ ok, data }) => {
                    if (ok && data.found && data.voucher_id) {
                        mostrarMensaje('Documento encontrado. Abriendo...', 'success');
                        window.location.href = `{{ url('/comprobantes') }}/${data.voucher_id}/contabilizacion`;
                        return;
                    }

                    if (data.document_url) {
                        mostrarMensaje('Documento encontrado. Aún no está contabilizado, abriendo...', 'success');
                        window.location.href = data.document_url;
                        return;
                    }

                    mostrarMensaje(data.message || 'No se encontró ningún documento con ese consecutivo.', 'error');
                    btnBuscar.disabled = false;
                    btnBuscar.innerText = 'Buscar';
                })
                .catch((error) => {
                    console.error('Error en buscador comercial:', error);
                    mostrarMensaje('Ocurrió un problema en el servidor al buscar.', 'error');
                    btnBuscar.disabled = false;
                    btnBuscar.innerText = 'Buscar';
                });
        }

        btnBuscar.addEventListener('click', ejecutarBusqueda);
        inputConsecutivo.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') ejecutarBusqueda();
        });

        // --- Buscador general de módulos (filtra las tarjetas del tablero en vivo) ---
        const inputModulo = document.getElementById('module-search');
        const tarjetas = Array.from(document.querySelectorAll('#modules-grid [data-module-name]'));
        const vacio = document.getElementById('modules-empty');

        inputModulo?.addEventListener('input', () => {
            const termino = inputModulo.value.trim().toLowerCase();
            let visibles = 0;

            tarjetas.forEach((tarjeta) => {
                const coincide = tarjeta.dataset.moduleName.includes(termino);
                tarjeta.classList.toggle('hidden', !coincide);
                if (coincide) visibles++;
            });

            vacio.classList.toggle('hidden', visibles !== 0);
        });
    });
    </script>
</x-app-layout>
