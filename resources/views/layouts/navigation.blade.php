@auth
        @php
            $quickModules = [
                ['label' => 'Ventas', 'route' => 'sales.index', 'active' => 'sales.*'],
                ['label' => 'Compras', 'route' => 'purchases.index', 'active' => 'purchases.*'],
                ['label' => 'Punto de venta', 'route' => 'pos.index', 'active' => 'pos.*'],
                ['label' => 'Inventario', 'route' => 'items.index', 'active' => 'items.*'],
                ['label' => 'Terceros', 'route' => 'third-parties.index', 'active' => 'third-parties.*'],
                ['label' => 'Nómina', 'route' => 'payroll.index', 'active' => 'payroll.*'],
                ['label' => 'Documento soporte', 'route' => 'support-documents.index', 'active' => 'support-documents.*'],
                ['label' => 'Contabilidad', 'route' => 'accounting.index', 'active' => 'accounting.*'],
                ['label' => 'PUC', 'route' => 'accounting.puc.index', 'active' => 'accounting.puc.*'],
                ['label' => 'Conciliación bancaria', 'route' => 'bank-reconciliation.index', 'active' => 'bank-reconciliation.*'],
                ['label' => 'Arqueo de caja', 'route' => 'cash-register.index', 'active' => 'cash-register.*'],
                ['label' => 'Logística', 'route' => 'logistics.index', 'active' => 'logistics.*'],
                ['label' => 'Documentos comerciales', 'route' => 'commercial-documents.quotations', 'active' => 'commercial-documents.*'],
                ['label' => 'Reportes', 'route' => 'reports.index', 'active' => 'reports.*'],
                ['label' => 'Indicadores financieros', 'route' => 'financial-dashboard.index', 'active' => 'financial-dashboard.*'],
                ['label' => 'Costos y márgenes', 'route' => 'costs.index', 'active' => 'costs.*'],
                ['label' => 'Cierres contables', 'route' => 'period-closing.index', 'active' => 'period-closing.*'],
                ['label' => 'Impuestos', 'route' => 'tax-drafts.index', 'active' => 'tax-drafts.*'],
                ['label' => 'TRM y monedas', 'route' => 'currency.index', 'active' => 'currency.*'],
                ['label' => 'Operaciones masivas', 'route' => 'bulk-operations.index', 'active' => 'bulk-operations.*'],
            ];
        @endphp
@endauth

@if ($standalone ?? false)
    @auth
        @php
            $standaloneCompanyRole = auth()->user()->companies()->where('companies.id', (int) session('company_id'))->first()?->pivot->role;
        @endphp
        <style>
            .su-quick-nav{position:relative;z-index:40;display:flex;align-items:center;gap:14px;max-width:1280px;margin:0 auto 18px;padding:10px 14px;border:1px solid #d7dfd8;border-radius:8px;background:#fff;color:#192522;font:600 14px/1.4 Arial,sans-serif;box-sizing:border-box}
            .su-quick-nav *{box-sizing:border-box}.su-quick-nav a{color:#227c70;text-decoration:none}.su-quick-nav a:hover{text-decoration:underline}.su-quick-nav .su-brand{font-weight:900;color:#192522}.su-quick-nav details{position:relative}.su-quick-nav summary{cursor:pointer;list-style:none;padding:6px 10px;border:1px solid #d7dfd8;border-radius:6px;color:#227c70}.su-quick-nav summary::-webkit-details-marker{display:none}.su-quick-nav summary:focus-visible,.su-quick-nav a:focus-visible{outline:2px solid #227c70;outline-offset:2px}.su-quick-nav .su-links{position:absolute;top:calc(100% + 6px);left:0;display:grid;min-width:220px;max-height:70vh;overflow-y:auto;padding:6px;border:1px solid #d7dfd8;border-radius:6px;background:#fff;box-shadow:0 10px 24px rgba(25,37,34,.15)}.su-quick-nav .su-links a{display:block;padding:8px 10px;border-radius:4px}.su-quick-nav .su-links a:hover{background:#edf7f4;text-decoration:none}.su-quick-nav .su-links a[aria-current=page]{background:#edf7f4;color:#192522}
            @media print{.su-quick-nav{display:none!important}}
        </style>
        <nav class="su-quick-nav" aria-label="Navegación principal">
            <a class="su-brand" href="{{ route('dashboard') }}">SU+</a>
            <a href="{{ route('dashboard') }}">Tablero</a>
            <details>
                <summary>Módulos <span aria-hidden="true">▾</span></summary>
                <div class="su-links">
                    @foreach ($quickModules as $module)
                        <a href="{{ route($module['route']) }}" @if (request()->routeIs($module['active'])) aria-current="page" @endif>{{ $module['label'] }}</a>
                    @endforeach
                    @if (in_array($standaloneCompanyRole, ['admin', 'owner'], true))
                        <a href="{{ route('admin.index') }}">Administración</a>
                    @endif
                </div>
            </details>
        </nav>
    @endauth
@else
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100" aria-label="Navegación principal">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden lg:-my-px lg:ms-10 lg:flex lg:space-x-4">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Tablero
                    </x-nav-link>
                    @auth
                        @php
                            $currentCompanyRole = auth()->user()->companies()->where('companies.id', (int) session('company_id'))->first()?->pivot->role;
                        @endphp
                        <div class="flex items-center">
                            <x-dropdown align="left" width="w-64">
                                <x-slot name="trigger">
                                    <button type="button" class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold text-[#227c70] hover:bg-[#edf7f4] focus:outline-none focus:ring-2 focus:ring-[#227c70]" :aria-expanded="open.toString()" aria-haspopup="true">
                                        Módulos <span aria-hidden="true">▾</span>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <div class="max-h-96 overflow-y-auto" aria-label="Accesos a módulos">
                                        @foreach ($quickModules as $module)
                                            <x-dropdown-link :href="route($module['route'])" :aria-current="request()->routeIs($module['active']) ? 'page' : null">
                                                {{ $module['label'] }}
                                            </x-dropdown-link>
                                        @endforeach
                                    </div>
                                </x-slot>
                            </x-dropdown>
                        </div>
                        @if(in_array($currentCompanyRole, ['admin', 'owner'], true))
                            <x-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.*')">
                                Administración
                            </x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden lg:flex lg:items-center lg:ms-6">
                @auth
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('dashboard')">
                            {{ __('Tablero') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
                @else
                    <span class="text-sm text-gray-600">Invitado</span>
                @endauth
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center lg:hidden">
                <button type="button" @click="open = ! open" aria-label="Menú de navegación" :aria-expanded="open.toString()" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden max-h-[75vh] overflow-y-auto lg:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            @auth
                <p class="px-4 pt-3 text-xs font-bold uppercase text-gray-500">Módulos</p>
                @foreach ($quickModules as $module)
                    <x-responsive-nav-link :href="route($module['route'])" :active="request()->routeIs($module['active'])">
                        {{ $module['label'] }}
                    </x-responsive-nav-link>
                @endforeach
                @if(in_array($currentCompanyRole ?? null, ['admin', 'owner'], true))
                    <x-responsive-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.*')">
                        Administración
                    </x-responsive-nav-link>
                @endif
            @endauth
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            @auth
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('dashboard')">
                    {{ __('Tablero') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
            @else
                <div class="px-4">
                    <span class="font-medium text-sm text-gray-600">Invitado</span>
                </div>
            @endauth
        </div>
    </div>
</nav>
@endif
