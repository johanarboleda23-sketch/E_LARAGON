<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Conciliación bancaria</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
            @if ($bankJournals->isNotEmpty())
                <form method="POST" action="{{ route('bank-reconciliation.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white p-3">
                    @csrf
                    <select name="journal_id" class="rounded-md border-slate-300 text-sm" required>
                        <option value="">Selecciona el diario bancario</option>
                        @foreach ($bankJournals as $journal)
                            <option value="{{ $journal->id }}" @selected(old('journal_id') == $journal->id)>{{ $journal->name }} · {{ $journal->paymentMethod->name }}{{ $journal->paymentMethod->account_number ? ' · '.$journal->paymentMethod->account_number : '' }}</option>
                        @endforeach
                    </select>
                    <input type="file" name="file" accept=".xlsx,.csv,.txt" class="max-w-xs text-sm" required>
                    <button class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white">Importar Excel / CSV</button>
                </form>
            @else
                <a href="{{ route('bank-reconciliation.journals') }}" class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white">Crea un diario para importar</a>
            @endif
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
        <a href="{{ route('bank-reconciliation.journals') }}" class="rounded-md border px-3 py-2 {{ $section === 'journals' ? 'border-sky-700 bg-sky-700 font-bold text-white' : 'border-slate-200 bg-white text-slate-700' }}">Diarios bancarios</a>
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
    @elseif ($section === 'journals')
        <section class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <form method="POST" action="{{ route('bank-reconciliation.journals.store') }}" class="grid content-start gap-4 rounded-lg border border-slate-200 bg-white p-5">
                @csrf
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Crear diario bancario</h2>
                    <p class="mt-1 text-sm text-slate-500">Cada diario representa una sola cuenta bancaria. Puedes crear un diario por cada banco o cuenta.</p>
                </div>
                <label class="grid gap-1 text-sm font-medium text-slate-700">Nombre del diario
                    <input type="text" name="name" maxlength="100" value="{{ old('name') }}" placeholder="Ej.: Bancolombia principal" class="rounded-md border-slate-300" required>
                </label>
                <label class="grid gap-1 text-sm font-medium text-slate-700">Cuenta bancaria
                    <select name="payment_method_id" class="rounded-md border-slate-300" required>
                        <option value="">Selecciona una cuenta bancaria</option>
                        @foreach ($bankAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old('payment_method_id') == $account->id)>{{ $account->name }}{{ $account->bank_name ? ' · '.$account->bank_name : '' }}{{ $account->account_number ? ' · '.$account->account_number : '' }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($bankAccounts->isEmpty())
                    <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">No hay cuentas bancarias configuradas con una cuenta PUC auxiliar del grupo 11.</p>
                @endif
                <button @disabled($bankAccounts->isEmpty()) class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Guardar diario</button>
            </form>

            <div class="rounded-lg border border-slate-200 bg-white p-5 lg:col-span-2">
                <h2 class="text-lg font-bold text-slate-900">Diarios configurados</h2>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($bankJournals as $journal)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <strong class="text-sm text-slate-900">{{ $journal->name }}</strong>
                                <p class="text-xs text-slate-500">{{ $journal->paymentMethod->name }} · {{ $journal->paymentMethod->bank_name ?: $journal->paymentMethod->account?->name }}{{ $journal->paymentMethod->account_number ? ' · '.$journal->paymentMethod->account_number : '' }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Activo</span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">Aún no has creado diarios bancarios.</p>
                    @endforelse
                </div>
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
                                @if ($statement->paymentMethod->bankJournal)
                                    <strong>{{ $statement->paymentMethod->bankJournal->name }}</strong><br>
                                @endif
                                <span class="text-xs text-slate-600">{{ $statement->paymentMethod->name }}</span>
                            @else
                                <form method="POST" action="{{ route('bank-reconciliation.assign-account', $statement) }}" class="flex gap-1">
                                    @csrf
                                    <select name="journal_id" class="max-w-40 rounded-md border-slate-300 text-xs" required>
                                        <option value="">Selecciona diario</option>
                                        @foreach ($bankJournals as $journal)
                                            <option value="{{ $journal->id }}">{{ $journal->name }} · {{ $journal->paymentMethod->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold text-white">Asignar</button>
                                </form>
                                @if ($bankJournals->isEmpty())
                                    <a class="text-xs text-sky-700 underline" href="{{ route('bank-reconciliation.journals') }}">Crea un diario primero</a>
                                @endif
                            @endif
                        </td>
                        <td class="p-3">
                            {{ $statement->status === 'reconciled' ? 'Conciliado' : 'Pendiente' }}
                            @if($statement->accountingVoucher)
                                <br><a class="text-xs text-sky-700 underline" target="_blank" href="{{ route('accounting.vouchers.accounting', $statement->accountingVoucher) }}">{{ $statement->accountingVoucher->consecutive }}</a>
                            @endif
                        </td>
                        <td class="p-3">
                            @if ($statement->status === 'pending')
                                @if ($statement->paymentMethod)
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded-md border border-sky-200 px-3 py-1 text-xs font-semibold text-sky-700" onclick="findSuggestions({{ $statement->id }}, '{{ route('bank-reconciliation.suggestions', $statement) }}', '{{ route('bank-reconciliation.reconcile', $statement) }}')">Buscar coincidencias exactas</button>
                                    </div>
                                    <div id="suggestions-{{ $statement->id }}" class="mt-2 text-xs text-slate-600"></div>
                                    @php($reopeningManualForm = (string) old('manual_reconcile_statement_id', session('manual_reconcile_statement_id')) === (string) $statement->id)
                                    <details class="relative mt-2">
                                        <summary class="cursor-pointer rounded-md bg-emerald-600 px-3 py-1 text-xs font-semibold text-white">Conciliar ▾</summary>
                                        <div class="mt-1 grid w-48 gap-1 rounded-md border border-slate-200 bg-white p-2 shadow-lg">
                                            <button type="button" class="rounded px-2 py-2 text-left text-xs hover:bg-slate-100" onclick="openReconciliation({{ $statement->id }}, 'invoice')">Conciliar factura</button>
                                            <button type="button" class="rounded px-2 py-2 text-left text-xs hover:bg-slate-100" onclick="openReconciliation({{ $statement->id }}, 'account')">Establecer cuenta PUC</button>
                                            <button type="button" class="rounded px-2 py-2 text-left text-xs hover:bg-slate-100" onclick="openReconciliation({{ $statement->id }}, 'transfer')">Transferencia interna</button>
                                        </div>
                                    </details>
                                    <dialog id="reconciliation-{{ $statement->id }}" data-reopen="{{ $reopeningManualForm ? old('reconciliation_mode', 'invoice') : '' }}" class="w-11/12 max-w-2xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/50">
                                        <div class="p-6">
                                            <div class="flex items-start justify-between gap-4">
                                                <div><h2 data-dialog-title class="text-lg font-bold text-slate-900"></h2><p class="mt-1 text-sm text-slate-600">{{ $statement->paymentMethod?->bankJournal?->name ?? $statement->paymentMethod?->name }} · {{ $statement->transaction_type === 'credit' ? 'Ingreso' : 'Egreso' }} · <strong>${{ number_format((float) $statement->amount, 2) }}</strong></p></div>
                                                <button type="button" class="text-xl text-slate-500" aria-label="Cerrar" onclick="this.closest('dialog').close()">×</button>
                                            </div>
                                            @if($reopeningManualForm && $errors->any())
                                                <p class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>
                                            @endif
                                            <form data-mode="invoice" method="POST" action="{{ route('bank-reconciliation.reconcile', $statement) }}" data-invoices-url="{{ route('bank-reconciliation.invoices', ['statement' => $statement->id, 'thirdParty' => '__PARTY__']) }}" data-old-invoice="{{ $reopeningManualForm ? old('invoice', '') : '' }}" class="mt-5 hidden gap-4" hidden>
                                                @csrf
                                                <input type="hidden" name="matched_type" value="manual">
                                                <input type="hidden" name="reconciliation_mode" value="invoice">
                                                <input type="hidden" name="manual_reconcile_statement_id" value="{{ $statement->id }}">
                                                <input type="hidden" name="amount" value="{{ number_format((float) $statement->amount, 2, '.', '') }}">
                                                <label class="grid gap-1 text-sm font-semibold">Buscar tercero
                                                    <select name="third_party_id" data-third-party-select required>
                                                        <option value="">Selecciona un tercero</option>
                                                        @foreach($thirdParties as $thirdParty)
                                                            @if ($statement->transaction_type === 'credit' ? $thirdParty->is_customer : $thirdParty->is_supplier)
                                                                <option value="{{ $thirdParty->id }}" @selected($reopeningManualForm && old('third_party_id') == $thirdParty->id)>{{ $thirdParty->name }}{{ $thirdParty->document ? ' · '.$thirdParty->document : '' }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="grid gap-1 text-sm font-semibold">Factura pendiente
                                                    <select name="invoice" data-invoice-select required disabled><option value="">Selecciona un tercero primero</option></select>
                                                </label>
                                                <p data-invoice-status class="text-sm text-slate-600" aria-live="polite"></p>
                                                <button type="submit" data-invoice-submit class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Registrar pago y conciliar</button>
                                            </form>
                                            <form data-mode="account" method="POST" action="{{ route('bank-reconciliation.reconcile', $statement) }}" class="mt-5 hidden gap-4" hidden>
                                                @csrf
                                                <input type="hidden" name="matched_type" value="manual">
                                                <input type="hidden" name="reconciliation_mode" value="account">
                                                <input type="hidden" name="manual_reconcile_statement_id" value="{{ $statement->id }}">
                                                <input type="hidden" name="amount" value="{{ number_format((float) $statement->amount, 2, '.', '') }}">
                                                <label class="grid gap-1 text-sm font-semibold">Cuenta PUC de contrapartida
                                                    <select name="counter_account_id" required>
                                                        <option value="">Busca una cuenta PUC</option>
                                                        @foreach($postingAccounts as $account)
                                                            <option value="{{ $account->id }}" @selected($reopeningManualForm && old('counter_account_id') == $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Crear comprobante y conciliar</button>
                                            </form>
                                            <form data-mode="transfer" data-transfer-url="{{ route('bank-reconciliation.transfer-candidates', $statement) }}" method="POST" action="{{ route('bank-reconciliation.transfer', $statement) }}" class="mt-5 hidden gap-4" hidden>
                                                @csrf
                                                <input type="hidden" name="reconciliation_mode" value="transfer">
                                                <input type="hidden" name="manual_reconcile_statement_id" value="{{ $statement->id }}">
                                                <label class="grid gap-1 text-sm font-semibold">Movimiento del otro banco
                                                    <select name="counter_statement_id" data-transfer-select required disabled><option value="">Cargando movimientos...</option></select>
                                                </label>
                                                <p data-transfer-status class="text-sm text-slate-600" aria-live="polite"></p>
                                                <button type="submit" data-transfer-submit disabled class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Conciliar transferencia</button>
                                            </form>
                                        </div>
                                    </dialog>
                                @else
                                    <span class="text-xs text-amber-700">Asigna un diario bancario antes de conciliar este movimiento.</span>
                                @endif
                            @else
                                @if(in_array($statement->matched_type, ['manual', 'transfer'], true) && $statement->accounting_voucher_id)
                                    <span class="text-xs text-slate-500">Comprobante publicado; usa un ajuste para corregirlo.</span>
                                @else
                                    <form method="POST" action="{{ route('bank-reconciliation.unmatch', $statement) }}">
                                        @csrf
                                        <button class="rounded-md border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">Desconciliar</button>
                                    </form>
                                @endif
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
    function openReconciliation(id, mode) {
        const dialog = document.getElementById(`reconciliation-${id}`);
        const titles = { invoice: 'Conciliar factura', account: 'Establecer cuenta PUC', transfer: 'Transferencia interna' };
        dialog.querySelector('[data-dialog-title]').textContent = titles[mode];
        dialog.querySelectorAll('form[data-mode]').forEach((form) => {
            const active = form.dataset.mode === mode;
            form.hidden = !active;
            form.classList.toggle('hidden', !active);
            form.classList.toggle('grid', active);
        });
        if (!dialog.open) dialog.showModal();
        if (mode === 'transfer') loadTransfers(dialog.querySelector('[data-mode="transfer"]'));
    }

    async function loadTransfers(form) {
        const select = form.querySelector('[data-transfer-select]');
        const status = form.querySelector('[data-transfer-status]');
        const submit = form.querySelector('[data-transfer-submit]');
        submit.disabled = true;
        select.replaceChildren(new Option('Cargando movimientos...', ''));
        select.disabled = true;
        status.textContent = '';
        try {
            const response = await fetch(form.dataset.transferUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('No fue posible consultar los movimientos del otro banco.');
            const data = await response.json();
            select.replaceChildren(new Option('Selecciona el movimiento correspondiente', ''), ...data.movements.map((movement) => new Option(movement.label, movement.id)));
            select.disabled = data.movements.length === 0;
            status.textContent = data.movements.length ? 'Solo aparecen movimientos pendientes del mismo valor y sentido contrario.' : 'No hay movimientos pendientes del otro banco con el mismo valor.';
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'Error al consultar los movimientos.';
        }
    }

    document.querySelectorAll('[data-transfer-select]').forEach((select) => {
        select.addEventListener('change', () => {
            select.form.querySelector('[data-transfer-submit]').disabled = !select.value;
        });
    });

    document.querySelectorAll('[data-third-party-select]').forEach((thirdPartySelect) => {
        const form = thirdPartySelect.closest('form');
        const invoiceSelect = form.querySelector('[data-invoice-select]');
        const status = form.querySelector('[data-invoice-status]');
        const submit = form.querySelector('[data-invoice-submit]');
        const placeholder = 'Selecciona un tercero primero';
        let requestNumber = 0;
        const syncSubmit = () => { submit.disabled = !invoiceSelect.value; };

        const resetInvoices = () => {
            invoiceSelect.replaceChildren(new Option(placeholder, ''));
            invoiceSelect.disabled = true;
            syncSubmit();
        };

        const loadInvoices = async () => {
            const currentRequest = ++requestNumber;
            if (!thirdPartySelect.value) {
                resetInvoices();
                status.textContent = '';
                return;
            }

            invoiceSelect.replaceChildren(new Option('Cargando facturas...', ''));
            invoiceSelect.disabled = true;
            syncSubmit();
            status.textContent = '';
            try {
                const url = form.dataset.invoicesUrl.replace('__PARTY__', encodeURIComponent(thirdPartySelect.value));
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    throw new Error('No fue posible consultar las facturas de este tercero.');
                }

                const data = await response.json();
                if (currentRequest !== requestNumber) return;
                const options = [new Option('Selecciona una factura pendiente', '')];
                data.invoices.forEach((invoice) => options.push(new Option(invoice.label, invoice.value)));
                invoiceSelect.replaceChildren(...options);
                invoiceSelect.disabled = data.invoices.length === 0;
                const oldInvoice = form.dataset.oldInvoice;
                if (oldInvoice && options.some((option) => option.value === oldInvoice)) {
                    invoiceSelect.value = oldInvoice;
                }
                syncSubmit();
                status.textContent = data.invoices.length
                    ? 'Selecciona la factura para registrar el pago por el valor del movimiento.'
                    : 'Este tercero no tiene facturas pendientes.';
            } catch (error) {
                if (currentRequest !== requestNumber) return;
                invoiceSelect.replaceChildren(new Option('No se pudieron cargar las facturas', ''));
                status.textContent = error instanceof Error ? error.message : 'Error al consultar las facturas.';
            }
        };

        thirdPartySelect.addEventListener('change', loadInvoices);
        invoiceSelect.addEventListener('change', syncSubmit);
        if (thirdPartySelect.value) {
            loadInvoices();
        } else {
            resetInvoices();
        }
    });

    document.querySelectorAll('dialog[data-reopen]').forEach((dialog) => {
        if (dialog.dataset.reopen) openReconciliation(dialog.id.replace('reconciliation-', ''), dialog.dataset.reopen);
    });

    async function findSuggestions(id, url, reconcileUrl) {
        const target = document.getElementById(`suggestions-${id}`);
        target.textContent = 'Buscando coincidencias...';
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('No fue posible consultar las coincidencias.');
            }
            const data = await response.json();
            target.replaceChildren();
            if (data.payments.length === 0) {
                target.textContent = 'No hay un pago con la misma referencia, cuenta, tipo e importe.';
                return;
            }
            data.payments.forEach((payment) => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = reconcileUrl;
                form.className = 'mb-2 flex items-center gap-2';
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                if (!token) {
                    throw new Error('No se encontró el token de seguridad de la sesión.');
                }
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = token;
                form.appendChild(csrf);
                [['matched_type', 'payment'], ['matched_id', String(payment.id)]].forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });
                const description = document.createElement('span');
                description.textContent = `Pago ${payment.reference} · ${payment.third_party || 'Sin tercero'} · $${Number(payment.amount).toLocaleString('es-CO', { minimumFractionDigits: 2 })}`;
                const button = document.createElement('button');
                button.type = 'submit';
                button.className = 'rounded-md bg-emerald-600 px-3 py-1 text-xs font-semibold text-white';
                button.textContent = 'Conciliar pago';
                form.append(description, button);
                target.appendChild(form);
            });
        } catch (error) {
            target.textContent = error instanceof Error ? error.message : 'Error al buscar coincidencias.';
        }
    }
</script>
</body>
</html>
