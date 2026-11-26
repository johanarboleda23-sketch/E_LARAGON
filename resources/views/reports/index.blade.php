<x-app-layout>
	<x-slot name="header">
		<div class="flex items-center justify-between">
			<h2 class="text-xl font-bold text-gray-800">Reportes e informes</h2>
			<a href="{{ route('dashboard') }}" class="rounded border px-3 py-2 text-xs">Tablero</a>
		</div>
	</x-slot>

	<div class="min-h-screen bg-gray-50 p-5">
		<div class="mx-auto max-w-7xl rounded-xl bg-white p-5 shadow-sm">
			@if (session('success'))
				<div role="status" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
			@endif
			@if (session('error'))
				<div role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
			@endif
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
						<option value="commercial" @selected(old('type', $type) === 'commercial')>Comprobantes comerciales (cotizaciones, órdenes, notas)</option>
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

			<section class="mt-6 rounded-2xl border border-[#d7dfd8] bg-[#f8faf8] p-5" aria-labelledby="retention-certificates-title">
				<div class="flex flex-wrap items-end justify-between gap-4">
					<div>
						<p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#227c70]">Gestión tributaria</p>
						<h3 id="retention-certificates-title" class="mt-1 text-lg font-black text-[#192522]">Certificados de retención</h3>
						<p class="mt-1 max-w-3xl text-xs leading-relaxed text-[#71807a]">Selecciona las cuentas de retención aplicables a cada tipo de documento. Los valores incluyen reversos contables.</p>
						<p class="mt-1 text-xs text-[#b65338]">Los terceros se identifican por nombre en el comprobante. Revisa destinatarios y valores antes de enviar.</p>
					</div>
					<form method="GET" action="{{ route('reports.index') }}" class="flex items-end gap-2">
						<label class="grid gap-1 text-xs font-bold text-[#52635b]">Año gravable
							<input name="retention_year" type="number" min="2000" max="2100" value="{{ $retentionYear }}" class="w-32 rounded-lg border-[#cbd6cf] text-sm" required>
						</label>
						<button class="rounded-lg border border-[#227c70] px-4 py-2.5 text-xs font-bold text-[#227c70]">Consultar año</button>
					</form>
				</div>
				<div class="mt-4 flex flex-wrap gap-2">
					<form id="retention-customer-form" method="POST" action="{{ route('retention-certificates.generate') }}" class="grid gap-2">
						@csrf <input type="hidden" name="kind" value="customer"><input type="hidden" name="taxable_year" value="{{ $retentionYear }}">
						<label class="grid gap-1 text-xs font-bold text-[#52635b]">Cuentas para certificados a clientes
							<select name="account_ids[]" multiple required class="h-28 min-w-64 rounded-lg border-[#cbd6cf] text-sm">
								@foreach ($retentionAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} | {{ $account->name }}</option>@endforeach
							</select>
						</label>
						<button class="rounded-lg bg-[#227c70] px-4 py-2.5 text-xs font-bold text-white">Generar certificados a clientes</button>
					</form>
					<form method="POST" action="{{ route('retention-certificates.generate') }}" class="grid gap-2">
						@csrf <input type="hidden" name="kind" value="supplier"><input type="hidden" name="taxable_year" value="{{ $retentionYear }}">
						<label class="grid gap-1 text-xs font-bold text-[#52635b]">Cuentas para solicitudes a proveedores
							<select name="account_ids[]" multiple required class="h-28 min-w-64 rounded-lg border-[#cbd6cf] text-sm">
								@foreach ($retentionAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} | {{ $account->name }}</option>@endforeach
							</select>
						</label>
						<button class="rounded-lg border border-[#227c70] px-4 py-2.5 text-xs font-bold text-[#227c70]">Generar solicitudes a proveedores</button>
					</form>
					<form method="POST" action="{{ route('retention-certificates.send-all') }}" onsubmit="return confirm('¿Enviar los certificados pendientes a los clientes del año {{ $retentionYear }}?')">
						@csrf <input type="hidden" name="kind" value="customer"><input type="hidden" name="taxable_year" value="{{ $retentionYear }}">
						<button class="rounded-lg bg-[#192522] px-4 py-2.5 text-xs font-bold text-white">Enviar certificados a clientes</button>
					</form>
					<form method="POST" action="{{ route('retention-certificates.send-all') }}" onsubmit="return confirm('¿Enviar las solicitudes pendientes a los proveedores del año {{ $retentionYear }}?')">
						@csrf <input type="hidden" name="kind" value="supplier"><input type="hidden" name="taxable_year" value="{{ $retentionYear }}">
						<button class="rounded-lg border border-[#192522] px-4 py-2.5 text-xs font-bold text-[#192522]">Enviar solicitudes a proveedores</button>
					</form>
				</div>
				<div class="mt-5 overflow-x-auto rounded-xl border border-[#d7dfd8] bg-white">
					<table class="w-full min-w-[900px] text-left text-xs">
						<thead class="bg-[#192522] uppercase tracking-wider text-white"><tr><th class="p-3">Tercero</th><th class="p-3">Tipo</th><th class="p-3">Correo</th><th class="p-3 text-right">Valor</th><th class="p-3">Estado</th><th class="p-3 text-right">Acciones</th></tr></thead>
						<tbody class="divide-y divide-[#e6ede8]">
							@forelse ($retentionCertificates as $certificate)
								<tr><td class="p-3 font-bold text-[#192522]">{{ $certificate->third_party_name }}{{ $certificate->third_party_document ? ' · '.$certificate->third_party_document : '' }}</td><td class="p-3">{{ $certificate->kind === 'customer' ? 'Certificado cliente' : 'Solicitud proveedor' }}</td><td class="p-3 {{ blank($certificate->recipient_email) ? 'font-bold text-red-600' : 'text-[#52635b]' }}">{{ $certificate->recipient_email ?: 'Sin correo registrado' }}</td><td class="p-3 text-right font-bold text-[#227c70]">${{ number_format($certificate->total_amount, 2, ',', '.') }}</td><td class="p-3"><span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $certificate->status === 'sent' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $certificate->status === 'sent' ? 'Enviado' : 'Borrador' }}</span></td><td class="p-3"><div class="flex justify-end gap-1"><a href="{{ route('retention-certificates.edit', $certificate) }}" class="rounded border border-[#d7dfd8] px-2 py-1 font-bold text-[#52635b]">Editar</a><a href="{{ route('retention-certificates.download', $certificate) }}" target="_blank" class="rounded border border-[#d7dfd8] px-2 py-1 font-bold text-[#227c70]">Descargar</a>@if($certificate->recipient_email)<form method="POST" action="{{ route('retention-certificates.send', $certificate) }}">@csrf<button class="rounded bg-[#227c70] px-2 py-1 font-bold text-white">Enviar</button></form>@endif<form method="POST" action="{{ route('retention-certificates.destroy', $certificate) }}" onsubmit="return confirm('¿Eliminar este registro?')">@csrf @method('DELETE')<button class="rounded border border-red-200 px-2 py-1 font-bold text-red-600">Eliminar</button></form></div></td></tr>
							@empty
								<tr><td colspan="6" class="p-6 text-center text-[#71807a]">Todavía no hay certificados generados para {{ $retentionYear }}.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</section>

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

	<button type="button" onclick="document.getElementById('cash-and-banks-panel').classList.toggle('hidden')" class="fixed bottom-6 right-6 z-40 flex items-center gap-2 rounded-full bg-[#227c70] px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-[#227c70]/30 hover:bg-[#1a5f55]">
		🏦 Caja / Bancos
	</button>

	<div id="cash-and-banks-panel" class="fixed bottom-24 right-6 z-40 hidden w-80 max-w-[90vw] rounded-2xl border border-[#d7dfd8] bg-white p-4 shadow-2xl">
		<div class="flex items-center justify-between">
			<h3 class="text-sm font-black text-[#192522]">Saldos de caja y bancos</h3>
			<button type="button" onclick="document.getElementById('cash-and-banks-panel').classList.add('hidden')" class="text-xs font-bold text-gray-400">✕</button>
		</div>
		<div class="mt-3 max-h-80 space-y-2 overflow-y-auto">
			@forelse($cashAndBanks as $row)
				<div class="rounded-lg border border-[#e6ede8] p-3">
					<p class="text-xs font-bold text-[#192522]">
						@if($row['is_cash'])
							💵 {{ $row['name'] }}
						@else
							🏦 {{ $row['account_number'] ? $row['account_number'].' ' : '' }}{{ $row['bank_name'] ?: $row['name'] }}
						@endif
					</p>
					<p class="mt-1 text-sm font-black {{ $row['balance'] >= 0 ? 'text-[#227c70]' : 'text-red-600' }}">${{ number_format($row['balance'], 2) }}</p>
				</div>
			@empty
				<p class="p-2 text-xs text-gray-500">Asocia formas de pago a una cuenta PUC en Administración para verlas aquí.</p>
			@endforelse
		</div>
	</div>
</x-app-layout>
