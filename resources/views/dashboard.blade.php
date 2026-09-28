<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-pink-500">E-ERP</p>
                <h2 class="text-2xl font-bold text-gray-800">Tablero principal</h2>
            </div>
            @guest
                <div class="flex gap-2 text-sm">
                    <a href="{{ route('login') }}" class="rounded-md bg-pink-600 px-3 py-2 font-semibold text-white hover:bg-pink-700">Ingresar</a>
                    <a href="{{ route('register') }}" class="rounded-md border border-pink-200 bg-white px-3 py-2 font-semibold text-pink-700 hover:bg-pink-50">Registrarse</a>
                </div>
            @else
                <form method="POST" action="{{ route('company.switch') }}" class="flex items-center gap-2">
                    @csrf
                    <label for="active-company" class="text-xs text-gray-500">Empresa</label>
                    <select id="active-company" name="company_id" onchange="this.form.submit()" class="rounded-md border-pink-200 py-1 text-xs text-pink-700">
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((int) session('company_id') === $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endguest
        </div>
    </x-slot>

    <div class="min-h-[calc(100vh-65px)] bg-gradient-to-br from-pink-50 via-white to-violet-50 px-5 py-10 sm:px-8">
        <div class="mx-auto max-w-6xl">
            <div class="mb-10 max-w-2xl">
                <p class="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-violet-600">Centro de operaciones</p>
                <h1 class="text-4xl font-black tracking-tight text-gray-900 sm:text-5xl">¿Qué necesitas hacer hoy?</h1>
                <p class="mt-4 text-base leading-7 text-gray-600">Accede rápidamente a los módulos de compras, inventario y facturación electrónica.</p>
            </div>

            <div class="gap-2" style="display:grid; grid-template-columns:repeat(2, 96px); width:200px;">
                <a href="{{ route('purchases.index') }}" class="group relative flex items-center gap-1 overflow-hidden rounded-md bg-pink-600 p-2 text-white shadow-md shadow-pink-200 transition duration-200 hover:-translate-y-1 hover:bg-pink-700 hover:shadow-lg" style="width:96px; height:44px;">
                    <span class="text-sm">🛒</span>
                    <h3 class="text-xs font-bold">Compras</h3>
                </a>

                <a href="{{ route('items.index') }}" class="group relative flex items-center gap-1 overflow-hidden rounded-md bg-violet-600 p-2 text-white shadow-md shadow-violet-200 transition duration-200 hover:-translate-y-1 hover:bg-violet-700 hover:shadow-lg" style="width:96px; height:44px;">
                    <span class="text-sm">📦</span>
                    <h3 class="text-xs font-bold">Inventario</h3>
                </a>

                <a href="{{ route('accounting.vouchers.index') }}" class="group relative flex items-center gap-1 overflow-hidden rounded-md bg-amber-500 p-2 text-white shadow-md shadow-amber-200 transition duration-200 hover:-translate-y-1 hover:bg-amber-600 hover:shadow-lg" style="width:96px; height:44px;">
                    <span class="text-sm">↗</span>
                    <h3 class="text-xs font-bold">EGRESO</h3>
                </a>
            </div>

            <p class="mt-8 mb-2 text-xs font-semibold uppercase tracking-widest text-gray-500">Accesos directos</p>
            <div class="flex flex-wrap gap-2" style="max-width:560px;">
                <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#7c3aed;">
                    <span>⚙</span> Administración
                </a>
                <a href="{{ route('third-parties.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#be185d;">
                    <span>♙</span> Terceros
                </a>
                <a href="{{ route('payroll.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#9333ea;">
                    <span>▦</span> Nómina
                </a>
                <a href="{{ route('support-documents.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#db2777;">
                    <span>▤</span> DOCUMENTO SOPORTE
                </a>
                <a href="{{ route('sales.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#2563eb;">
                    <span>🧾</span> Factura de venta
                </a>
                <a href="{{ route('commercial-documents.quotations') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#0f766e;">
                    <span>▧</span> Cotizaciones
                </a>
                <a href="{{ route('commercial-documents.sales-orders') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#2563eb;">
                    <span>⇢</span> Órdenes de venta
                </a>
                <a href="{{ route('commercial-documents.remissions') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#0891b2;">
                    <span>▤</span> Remisiones
                </a>
                <a href="{{ route('commercial-documents.purchase-orders') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#d97706;">
                    <span>🛒</span> Órdenes de compra
                </a>
                <a href="{{ route('commercial-documents.customer-credit-notes') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#059669;">
                    <span>↶</span> Nota crédito clientes
                </a>
                <a href="{{ route('commercial-documents.supplier-debit-notes') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#c2410c;">
                    <span>↷</span> Nota débito proveedores
                </a>
                <a href="{{ route('accounting.vouchers.index', ['type' => 'egreso']) }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#1f2937;">
                    <span>▤</span> Comprobantes
                </a>
                <a href="{{ route('accounting.vouchers.index', ['type' => 'egreso']) }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#d97706;">
                    <span>↗</span> Egreso / gasto
                </a>
                <a href="{{ route('accounting.vouchers.index', ['type' => 'recibo_caja']) }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#0891b2;">
                    <span>▣</span> Recibo de caja
                </a>
                <a href="{{ route('accounting.puc.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#4f46e5;">
                    <span>◎</span> PUC
                </a>
                <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#0f766e;">
                    <span>▥</span> Reportes financieros
                </a>
                <a href="{{ route('items.create') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#059669;">
                    <span>＋</span> Nuevo producto
                </a>
                <a href="{{ route('purchases.index') }}#dian" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#ea580c;">
                    <span>⚡</span> DIAN / XML
                </a>
                <a href="{{ route('bulk-operations.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#334155;">
                    <span>⇪</span> Operaciones masivas
                </a>
                <a href="{{ route('logistics.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90" style="background:#166534;">
                    <span>🚚</span> Logística
                </a>
            </div>

            <div class="mt-8 flex flex-wrap gap-3 text-sm text-gray-500">
                <span class="rounded-full border border-gray-200 bg-white/80 px-4 py-2">Stock actualizado por compras</span>
                <span class="rounded-full border border-gray-200 bg-white/80 px-4 py-2">Movimientos auditables</span>
                <span class="rounded-full border border-gray-200 bg-white/80 px-4 py-2">Retenciones configurables</span>
            </div>
        </div>
    </div>

    <script>
        function moduleComingSoon(event, moduleName) {
            event.preventDefault();
            window.alert('El módulo ' + moduleName + ' será habilitado en el siguiente paso.');
            return false;
        }
    </script>
</x-app-layout>
