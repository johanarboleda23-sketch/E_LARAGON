<x-app-layout>
	<x-slot name="header">
		<div class="flex items-center justify-between">
			<h2 class="text-xl font-bold text-gray-800">Reportes e informes</h2>
			<a href="{{ route('dashboard') }}" class="rounded border px-3 py-2 text-xs">Tablero</a>
		</div>
	</x-slot>

	<div class="min-h-screen bg-gray-50 p-5">
		<div class="mx-auto max-w-7xl rounded-xl bg-white p-5 shadow-sm">
			<form method="GET" action="{{ route('reports.index') }}" class="grid gap-3 md:grid-cols-4">
				<div>
					<label class="mb-1 block text-xs font-semibold">Desde</label>
					<input type="date" name="from" value="{{ old('from', $from) }}" class="w-full rounded border-gray-300 text-sm">
				</div>
				<div>
					<label class="mb-1 block text-xs font-semibold">Hasta</label>
					<input type="date" name="to" value="{{ old('to', $to) }}" class="w-full rounded border-gray-300 text-sm">
				</div>
				<div>
					<label class="mb-1 block text-xs font-semibold">Módulo</label>
					<select name="type" class="w-full rounded border-gray-300 text-sm">
						<option value="all" @selected(old('type', $type) === 'all')>Todos</option>
						<option value="purchases" @selected(old('type', $type) === 'purchases')>Compras</option>
						<option value="sales" @selected(old('type', $type) === 'sales')>Ventas</option>
						<option value="support" @selected(old('type', $type) === 'support')>Documentos soporte</option>
						<option value="payroll" @selected(old('type', $type) === 'payroll')>Nómina</option>
						<option value="accounting" @selected(old('type', $type) === 'accounting')>Movimientos PUC</option>
						<option value="taxes" @selected(old('type', $type) === 'taxes')>Impuestos (IVA y retefuente)</option>
					</select>
				</div>
				<button class="self-end rounded bg-pink-600 px-3 py-2 text-sm font-bold text-white">Consultar</button>
				<div>
					<label class="mb-1 block text-xs font-semibold">Cuenta PUC desde</label>
					<input type="text" name="account_from" value="{{ old('account_from', $accountFrom) }}" placeholder="Ej: 2365" class="w-full rounded border-gray-300 text-sm">
				</div>
				<div>
					<label class="mb-1 block text-xs font-semibold">Cuenta PUC hasta</label>
					<input type="text" name="account_to" value="{{ old('account_to', $accountTo) }}" placeholder="Ej: 2365" class="w-full rounded border-gray-300 text-sm">
				</div>
				<div>
					<label class="mb-1 block text-xs font-semibold">Tercero (contiene)</label>
					<input type="text" name="third_party" value="{{ old('third_party', $thirdParty) }}" placeholder="Ej: Proveedor S.A.S" class="w-full rounded border-gray-300 text-sm">
				</div>
				<p class="col-span-2 self-end text-xs text-gray-500">La cuenta PUC aplica solo al módulo "Movimientos PUC" (usa la misma cuenta en ambos campos para consultar una sola, o déjalas vacías para ver todos los movimientos auxiliares). El filtro de tercero también aplica a ese módulo, para ver el movimiento auxiliar de una sola persona o empresa.</p>
			</form>

			@if ($errors->any())
				<div role="alert" class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
					<ul class="list-inside list-disc space-y-1">
						@foreach ($errors->all() as $error)
							<li>{{ $error }}</li>
						@endforeach
					</ul>
				</div>
			@endif

			<div class="mt-5 flex flex-wrap gap-2">
				<a href="{{ route('reports.excel', request()->query()) }}" class="rounded bg-emerald-600 px-3 py-2 text-xs font-bold text-white">⇩ Descargar Excel</a>
				<a href="{{ route('reports.pdf', request()->query()) }}" target="_blank" class="rounded bg-red-600 px-3 py-2 text-xs font-bold text-white">▣ Ver / guardar PDF</a>
			</div>

			@if (in_array($type, ['accounting', 'taxes'], true) && $rows->isNotEmpty())
				<div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
					@foreach ($rows->groupBy('type') as $group => $items)
						<div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
							<p class="text-xs font-semibold text-gray-500">{{ $group }}</p>
							<p class="mt-1 text-lg font-bold text-gray-800">${{ number_format($items->sum('total'), 2) }}</p>
						</div>
					@endforeach
				</div>
			@endif

			<div class="mt-5 overflow-x-auto">
				<table class="w-full text-left text-sm">
					<thead class="border-b bg-gray-50 text-xs uppercase">
						<tr>
							<th class="p-2">Fecha</th>
							<th class="p-2">Tipo</th>
							<th class="p-2">Documento</th>
							<th class="p-2">Tercero</th>
							<th class="p-2 text-right">Total</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($rows as $row)
							<tr class="border-b">
								<td class="p-2">{{ $row['date'] }}</td>
								<td class="p-2">{{ $row['type'] }}</td>
								<td class="p-2">{{ $row['document'] }}</td>
								<td class="p-2">{{ $row['third_party'] }}</td>
								<td class="p-2 text-right">${{ number_format($row['total'], 2) }}</td>
							</tr>
						@empty
							<tr>
								<td colspan="5" class="p-4 text-center text-gray-500">No hay información en el período.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
</x-app-layout>
