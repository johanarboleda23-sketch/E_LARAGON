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
        <form method="POST" action="{{ route('bank-reconciliation.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white p-3">
            @csrf
            <input type="file" name="file" accept=".xlsx,.csv,.txt" class="max-w-xs text-sm" required>
            <button class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white">Importar Excel / CSV</button>
        </form>
    </div>

    @if (session('success'))
        <div class="mt-5 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-5 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Movimientos</p><strong class="mt-1 block text-xl">{{ $summary['count'] }}</strong></div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase text-amber-700">Pendiente</p><strong class="mt-1 block text-xl text-amber-900">${{ number_format($summary['pending'], 2) }}</strong></div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs uppercase text-emerald-700">Conciliado</p><strong class="mt-1 block text-xl text-emerald-900">${{ number_format($summary['reconciled'], 2) }}</strong></div>
    </div>

    <div class="mt-6 flex gap-2 text-sm">
        <a href="{{ route('bank-reconciliation.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 {{ ! $status ? 'font-bold text-sky-700' : '' }}">Todos</a>
        <a href="{{ route('bank-reconciliation.index', ['status' => 'pending']) }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 {{ $status === 'pending' ? 'font-bold text-sky-700' : '' }}">Pendientes</a>
        <a href="{{ route('bank-reconciliation.index', ['status' => 'reconciled']) }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 {{ $status === 'reconciled' ? 'font-bold text-sky-700' : '' }}">Conciliados</a>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="p-3 text-left">Fecha</th>
                    <th class="p-3 text-left">Referencia / descripción</th>
                    <th class="p-3 text-left">Tipo</th>
                    <th class="p-3 text-right">Importe</th>
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
                    <tr><td colspan="6" class="p-8 text-center text-slate-400">No hay movimientos importados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $statements->links() }}</div>
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
