<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-[#d96b4c]">Reportes / Certificados</p><h2 class="mt-1 text-xl font-black text-[#192522]">Editar documento</h2></div>
            <a href="{{ route('reports.index', ['retention_year' => $certificate->taxable_year]) }}" class="rounded-lg border border-[#d7dfd8] bg-white px-3 py-2 text-sm font-bold text-[#227c70]">&larr; Volver a reportes</a>
        </div>
    </x-slot>
    <div class="min-h-screen bg-[#f3f5f1] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-3xl rounded-2xl border border-[#d7dfd8] bg-white p-6 shadow-sm sm:p-8">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#227c70]">{{ $certificate->kind === 'customer' ? 'Certificado a cliente' : 'Solicitud a proveedor' }}</p>
            <h1 class="mt-2 text-2xl font-black text-[#192522]">{{ $certificate->third_party_name }}</h1>
            <p class="mt-1 text-sm text-[#71807a]">Año gravable {{ $certificate->taxable_year }} · Valor de referencia ${{ number_format($certificate->total_amount, 2, ',', '.') }}</p>
            <form method="POST" action="{{ route('retention-certificates.update', $certificate) }}" class="mt-6 grid gap-4">
                @csrf @method('PATCH')
                <label class="grid gap-1 text-sm font-bold text-[#52635b]">Correo destinatario<input type="email" name="recipient_email" value="{{ old('recipient_email', $certificate->recipient_email) }}" class="rounded-lg border-[#cbd6cf] text-sm" maxlength="255"></label>
                <label class="grid gap-1 text-sm font-bold text-[#52635b]">Asunto<input name="subject" value="{{ old('subject', $certificate->subject) }}" class="rounded-lg border-[#cbd6cf] text-sm" maxlength="255" required></label>
                <label class="grid gap-1 text-sm font-bold text-[#52635b]">Mensaje<textarea name="body" rows="12" class="rounded-lg border-[#cbd6cf] text-sm" required>{{ old('body', $certificate->body) }}</textarea></label>
                <div class="flex flex-wrap justify-end gap-2"><a href="{{ route('retention-certificates.download', $certificate) }}" target="_blank" class="rounded-lg border border-[#d7dfd8] px-4 py-2.5 text-sm font-bold text-[#52635b]">Vista previa</a><button class="rounded-lg bg-[#227c70] px-5 py-2.5 text-sm font-bold text-white">Guardar cambios</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
