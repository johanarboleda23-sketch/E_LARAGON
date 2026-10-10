<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Operaciones masivas</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50">
<div class="mx-auto max-w-6xl px-6 py-8">
    <a href="{{ route('dashboard') }}" class="text-sm text-teal-700">&larr; Volver al tablero</a>
    <h1 class="mt-3 text-2xl font-bold text-gray-900">Operaciones masivas</h1>
    <p class="text-sm text-gray-500">Selecciona documentos y aplica una acción a todos a la vez: imprimir, descargar o enviar por correo.</p>

    @if (session('success'))
        <div class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    <div class="mt-6 flex flex-wrap gap-2">
        @foreach ($families as $key => $configuration)
            <a href="{{ route('bulk-operations.index', ['family' => $key]) }}"
               class="rounded-md px-3 py-2 text-sm font-semibold {{ $family === $key ? 'bg-teal-700 text-white' : 'bg-white text-gray-700 border border-gray-200' }}">
                {{ $configuration['label'] }}
            </a>
        @endforeach
    </div>

    <form id="bulk-form" class="mt-6" onsubmit="return false;">
        <input type="hidden" name="family" value="{{ $family }}">

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3"><input type="checkbox" onclick="document.querySelectorAll('.doc-check').forEach(c => c.checked = this.checked)"></th>
                        <th class="p-3 text-left">Consecutivo</th>
                        <th class="p-3 text-left">Tercero</th>
                        <th class="p-3 text-left">Fecha</th>
                        <th class="p-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($documents as $document)
                        @php
                            $consecutive = $document->consecutive ?? $document->invoice_number ?? $document->id;
                            $party = $document->third_party_name ?? $document->customer_name ?? $document->provider ?? $document->third_party ?? ($document->supplier->name ?? '');
                            $date = $document->document_date ?? $document->sale_date ?? $document->purchase_date ?? $document->voucher_date ?? null;
                            $total = $document->total ?? $document->total_pagar ?? $document->total_debit ?? 0;
                        @endphp
                        <tr>
                            <td class="p-3"><input type="checkbox" class="doc-check" name="ids[]" value="{{ $document->id }}"></td>
                            <td class="p-3 font-semibold">{{ $consecutive }}</td>
                            <td class="p-3">{{ $party }}</td>
                            <td class="p-3">{{ $date?->format('d/m/Y') }}</td>
                            <td class="p-3 text-right">${{ number_format($total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6 text-center text-gray-400">No hay documentos para esta familia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex gap-3">
            <button type="button" onclick="submitBulk('{{ route('bulk-operations.print') }}', '_blank')" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Imprimir seleccionados</button>
            <button type="button" onclick="submitBulk('{{ route('bulk-operations.download') }}')" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Descargar ZIP</button>
            <button type="button" onclick="submitBulk('{{ route('bulk-operations.email') }}')" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Enviar por correo</button>
        </div>
    </form>
</div>

<script>
    function submitBulk(action, target) {
        const form = document.getElementById('bulk-form');
        const ids = Array.from(document.querySelectorAll('.doc-check:checked')).map(c => c.value);

        if (ids.length === 0) {
            alert('Selecciona al menos un documento.');
            return;
        }

        const submitForm = document.createElement('form');
        submitForm.method = 'POST';
        submitForm.action = action;
        if (target) {
            submitForm.target = target;
        }

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        submitForm.appendChild(csrf);

        const family = document.createElement('input');
        family.type = 'hidden';
        family.name = 'family';
        family.value = '{{ $family }}';
        submitForm.appendChild(family);

        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            submitForm.appendChild(input);
        });

        document.body.appendChild(submitForm);
        submitForm.submit();
        submitForm.remove();
    }
</script>
</body>
</html>
