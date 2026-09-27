<x-app-layout>
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="text-xl font-bold text-gray-800">Plan Único de Cuentas (PUC)</h2><a href="{{ route('accounting.vouchers.index') }}" class="rounded-md border px-3 py-2 text-xs">Comprobantes</a></div></x-slot>
    <div class="min-h-screen bg-gray-50 p-5"><div class="mx-auto grid max-w-7xl gap-5 lg:grid-cols-[340px_1fr]">
        <section class="rounded-xl bg-white p-5 shadow-sm"><h3 class="mb-4 font-bold">Nueva cuenta</h3>
            <form method="POST" action="{{ route('accounting.puc.store') }}" class="space-y-3">@csrf
                <input name="code" required placeholder="Código (ej. 110505)" class="w-full rounded border-gray-300 text-sm">
                <input name="name" required placeholder="Nombre de la cuenta" class="w-full rounded border-gray-300 text-sm">
                <select name="class" required class="w-full rounded border-gray-300 text-sm">@foreach(range(1,9) as $class)<option value="{{ $class }}">Clase {{ $class }}</option>@endforeach</select>
                <select name="account_type" required class="w-full rounded border-gray-300 text-sm"><option value="auxiliar">Auxiliar</option><option value="mayor">Cuenta mayor</option><option value="clase">Clase</option></select>
                <select name="parent_id" class="w-full rounded border-gray-300 text-sm"><option value="">Sin cuenta padre</option>@foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</option>@endforeach</select>
                <select name="nature" required class="w-full rounded border-gray-300 text-sm"><option value="debit">Débito</option><option value="credit">Crédito</option></select>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="has_due_date" value="1"> Maneja vencimiento / cartera</label>
                <button class="w-full rounded bg-pink-600 px-3 py-2 text-sm font-semibold text-white">Guardar cuenta</button>
            </form>
        </section>
        <section class="rounded-xl bg-white p-5 shadow-sm"><h3 class="mb-4 font-bold">Cuentas mayores y auxiliares</h3>
            @if(session('success'))<div class="mb-3 rounded bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>@endif
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b bg-gray-50 text-xs uppercase"><tr><th class="p-2">Código</th><th class="p-2">Cuenta</th><th class="p-2">Tipo</th><th class="p-2">Padre</th><th class="p-2">Vencimiento</th><th class="p-2">Estado</th></tr></thead><tbody>
                @foreach($accounts as $account)<tr class="border-b"><td class="p-2 font-mono">{{ $account->code }}</td><td class="p-2"><form method="POST" action="{{ route('accounting.puc.update', $account) }}" class="flex flex-wrap gap-1">@csrf @method('PUT')<input name="name" value="{{ $account->name }}" required class="w-48 rounded border-gray-300 text-xs"><select name="account_type" class="rounded border-gray-300 text-xs"><option value="clase" @selected($account->account_type === 'clase')>Clase</option><option value="mayor" @selected($account->account_type === 'mayor')>Mayor</option><option value="auxiliar" @selected($account->account_type === 'auxiliar')>Auxiliar</option></select><select name="nature" class="rounded border-gray-300 text-xs"><option value="debit" @selected($account->nature === 'debit')>Débito</option><option value="credit" @selected($account->nature === 'credit')>Crédito</option></select><label class="text-xs"><input type="checkbox" name="has_due_date" value="1" @checked($account->has_due_date)> Vence</label><label class="text-xs"><input type="checkbox" name="active" value="1" @checked($account->active)> Activa</label><button class="rounded bg-gray-800 px-2 py-1 text-xs text-white">Guardar</button></form></td><td class="p-2">{{ ucfirst($account->account_type) }}</td><td class="p-2 text-gray-500">{{ $account->parent?->code ?? '-' }}</td><td class="p-2">{{ $account->has_due_date ? 'Sí' : 'No' }}</td><td class="p-2">{{ $account->active ? 'Activa' : 'Inactiva' }}</td></tr>@endforeach
            </tbody></table></div>
        </section>
    </div></div>
</x-app-layout>
