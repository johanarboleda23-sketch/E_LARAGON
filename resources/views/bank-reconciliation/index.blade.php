<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conciliación bancaria</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-slate-50 text-slate-800">
<div class="mx-auto max-w-7xl px-6 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-sky-700">&larr; Volver al tablero</a>
            <h1 class="mt-3 text-2xl font-bold text-slate-900">Conciliación bancaria</h1>
            <p class="text-sm text-slate-500">Importa el extracto, revisa coincidencias y confirma cada movimiento.</p>
        </div>
        @if ($section === 'movements')
            <form method="POST" action="{{ route('bank-reconciliation.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white p-3">
                @csrf
                <select name="payment_method_id" class="rounded-md border-slate-300 text-sm" required>
                    <option value="">Selecciona el banco</option>
                    @foreach ($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('payment_method_id', $paymentMethodId) == $account->id)>{{ $account->name }}{{ $account->account_number ? ' · '.$account->account_number : '' }}</option>
                    @endforeach
                </select>
                <input type="file" name="file" accept=".xlsx,.csv,.txt" class="max-w-xs text-sm" required>
                <button class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white">Importar Excel / CSV</button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="mt-5 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-5 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('bank-reconciliation.index') }}" class="rounded-md border px-3 py-2 {{ $section === 'movements' ? 'border-sky-700 bg-sky-700 font-bold text-white' : 'border-slate-200 bg-white text-slate-700' }}">Movimientos y conciliación</a>
        <a href="{{ route('bank-reconciliation.balances') }}" class="rounded-md border px-3 py-2 {{ $section === 'balances' ? 'border-sky-700 bg-sky-700 font-bold text-white' : 'border-slate-200 bg-white text-slate-700' }}">Saldos iniciales y saldo diario</a>
    </div>

    @if ($section === 'balances')
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold text-slate-900">Consultar saldo final del día</h2>
            <p class="mt-1 text-sm text-slate-500">El saldo del extracto parte del saldo inicial y suma los movimientos importados desde la fecha indicada. El saldo contable usa los comprobantes asociados a la cuenta PUC.</p>
            <form method="GET" action="{{ route('bank-reconciliation.balances') }}" class="mt-4 flex flex-wrap items-end gap-3">
                <label class="grid gap-1 text-sm font-medium text-slate-700">
                    Cuenta bancaria
                    <select name="payment_method_id" class="rounded-md border-slate-300" required>
                        @foreach ($bankAccounts as $account)
                            <option value="{{ $account->id }}" @selected($selectedAccount?->id === $account->id)>{{ $account->name }}{{ $account->account_number ? ' · '.$account->account_number : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-medium text-slate-700">
                    Fecha
                    <input type="date" name="date" value="{{ $date }}" class="rounded-md border-slate-300" required>
                </label>
                <button class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white">Consultar</button>
            </form>
            @if ($selectedAccount && $openingBalance && $date >= $openingBalance->effective_date->toDateString() && $balance)
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4"><p class="text-xs uppercase text-sky-700">Saldo bancario al {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</p><strong class="mt-1 block text-xl text-sky-900">${{ number_format($balance['bank'], 2) }}</strong></div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Saldo contable</p><strong class="mt-1 block text-xl text-slate-900">{{ $balance['ledger'] === null ? 'No disponible' : '$'.number_format($balance['ledger'], 2) }}</strong></div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs uppercase text-emerald-700">Diferencia bancaria menos contable</p><strong class="mt-1 block text-xl text-emerald-900">{{ $balance['difference'] === null ? '—' : '$'.number_format($balance['difference'], 2) }}</strong></div>
                </div>
                @if (! $balance['ledger_configured'])
                    <p class="mt-3 text-sm text-amber-700">Asocia esta cuenta bancaria con una cuenta PUC de bancos para calcular su saldo contable.</p>
                @endif
            @elseif ($selectedAccount)
                <p class="mt-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">Registra un saldo inicial para esta cuenta, o consulta una fecha igual o posterior al saldo inicial.</p>
            @endif
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold text-slate-900">Saldos iniciales por cuenta</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                @forelse ($bankAccounts as $account)
                    @php($opening = $openingBalances->get($account->id))
                    <form method="POST" action="{{ route('bank-reconciliation.opening-balances.store') }}" class="grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="payment_method_id" value="{{ $account->id }}">
                        <div class="sm:col-span-2">
                            <strong class="text-sm text-slate-900">{{ $account->name }}</strong>
                            <p class="text-xs text-slate-500">{{ $account->bank_name ?: $account->account?->name }}{{ $account->account_number ? ' · '.$account->account_number : '' }}</p>
                        </div>
                        <label class="grid gap-1 text-xs font-medium text-slate-600">Saldo de apertura del día
                            <input type="number" step="0.01" name="opening_balance" value="{{ old('payment_method_id') == $account->id ? old('opening_balance') : ($opening?->opening_balance ?? '') }}" class="rounded-md border-slate-300 text-sm" required>
                        </label>
                        <label class="grid gap-1 text-xs font-medium text-slate-600">Fecha desde la que aplica
                            <input type="date" name="effective_date" value="{{ old('payment_method_id') == $account->id ? old('effective_date') : ($opening?->effective_date?->toDateString() ?? now()->toDateString()) }}" class="rounded-md border-slate-300 text-sm" required>
                        </label>
                        <button class="rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white sm:col-span-2">{{ $opening ? 'Actualizar saldo inicial' : 'Guardar saldo inicial' }}</button>
                    </form>
                @empty
                    <p class="text-sm text-slate-500">No hay cuentas bancarias configuradas. Configura una cuenta bancaria y su cuenta PUC desde Administración.</p>
                @endforelse
            </div>
        </section>
    @else
        @if ($unassignedCount > 0)
            <div class="mt-5 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $unassignedCount }} movimientos históricos no tienen cuenta bancaria asignada. Asócialos antes de usarlos en los saldos.</div>
        @endif

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Movimientos</p><strong class="mt-1 block text-xl">{{ $summary['count'] }}</strong></div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase text-amber-700">Pendiente</p><strong class="mt-1 block text-xl text-amber-900">${{ number_format($summary['pending'], 2) }}</strong></div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs uppercase text-emerald-700">Conciliado</p><strong class="mt-1 block text-xl text-emerald-900">${{ number_format($summary['reconciled'], 2) }}</strong></div>
    </div>

    <form method="GET" action="{{ route('bank-reconciliation.index') }}" class="mt-6 flex flex-wrap items-center gap-2 text-sm">
        <select name="payment_method_id" class="rounded-md border-slate-300">
            <option value="">Todos los bancos</option>
            @foreach ($bankAccounts as $account)
                <option value="{{ $account->id }}" @selected($paymentMethodId === $account->id)>{{ $account->name }}{{ $account->account_number ? ' · '.$account->account_number : '' }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-slate-200 bg-white px-3 py-2">Filtrar</button>
    </form>
    <div class="mt-4 flex gap-2 text-sm">
        <a href="{{ route('bank-reconciliation.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 {{ ! $status ? 'font-bold text-sky-700' : '' }}">Todos</a>
        <a href="{{ route('bank-reconciliation.index', ['status' => 'pending', 'payment_method_id' => $paymentMethodId]) }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 {{ $status === 'pending' ? 'font-bold text-sky-700' : '' }}">Pendientes</a>
        <a href="{{ route('bank-reconciliation.index', ['status' => 'reconciled', 'payment_method_id' => $paymentMethodId]) }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 {{ $status === 'reconciled' ? 'font-bold text-sky-700' : '' }}">Conciliados</a>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="p-3 text-left">Fecha</th>
                    <th class="p-3 text-left">Referencia / descripción</th>
                    <th class="p-3 text-left">Tipo</th>
                    <th class="p-3 text-right">Importe</th>
                    <th class="p-3 text-left">Cuenta bancaria</th>
                    <th class="p-3 text-left">Estado</th>
                    <th class="p-3 text-left">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($statements as $statement)
                    <tr>
                        <td class="p-3 whitespace-nowrap">{{ $statement->transaction_date->format('d/m/Y') }}</td>
                        <td class="p-3"><strong>{{ $statement->reference ?: 'Sin referencia' }}</strong><br><span class="text-xs text-slate-500">{{ $statement->description }}</span></td>
                        <td class="p-3">{{ $statement->transaction_type === 'credit' ? 'Ingreso' : 'Egreso' }}</td>
                        <td class="p-3 text-right font-semibold">${{ number_format($statement->amount, 2) }}</td>
                        <td class="p-3">
                            @if ($statement->paymentMethod)
                                {{ $statement->paymentMethod->name }}
                            @else
                                <form method="POST" action="{{ route('bank-reconciliation.assign-account', $statement) }}" class="flex gap-1">
                                    @csrf
                                    <select name="payment_method_id" class="max-w-40 rounded-md border-slate-300 text-xs" required>
                                        <option value="">Selecciona banco</option>
                                        @foreach ($bankAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold text-white">Asignar</button>
                                </form>
                            @endif
                        </td>
                        <td class="p-3">{{ $statement->status === 'reconciled' ? 'Conciliado' : 'Pendiente' }}</td>
                        <td class="p-3">
                            @if ($statement->status === 'pending')
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-md border border-sky-200 px-3 py-1 text-xs font-semibold text-sky-700" onclick="findSuggestions({{ $statement->id }}, '{{ route('bank-reconciliation.suggestions', $statement) }}')">Buscar coincidencias</button>
                                    <form method="POST" action="{{ route('bank-reconciliation.reconcile', $statement) }}" class="flex gap-1">
                                        @csrf
                                        <input type="hidden" name="matched_type" value="manual">
                                        <button class="rounded-md bg-emerald-600 px-3 py-1 text-xs font-semibold text-white">Conciliar manual</button>
                                    </form>
                                </div>
                                <div id="suggestions-{{ $statement->id }}" class="mt-2 text-xs text-slate-600"></div>
                            @else
                                <form method="POST" action="{{ route('bank-reconciliation.unmatch', $statement) }}">
                                    @csrf
                                    <button class="rounded-md border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">Desconciliar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-slate-400">No hay movimientos importados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $statements->links() }}</div>
    @endif
</div>
<script>
    async function findSuggestions(id, url) {
        const target = document.getElementById(`suggestions-${id}`);
        target.textContent = 'Buscando coincidencias...';
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        const sales = data.sales.map((sale) => `Venta ${sale.invoice_number} - ${sale.customer_name}`).join(', ');
        const purchases = data.purchases.map((purchase) => `Compra ${purchase.invoice_number} - ${purchase.provider}`).join(', ');
        target.textContent = sales || purchases ? `Coincidencias: ${[sales, purchases].filter(Boolean).join(' | ')}` : 'No se encontraron coincidencias por importe y fecha.';
    }
</script>
</body>
</html>
