<x-app-layout>
<x-slot name="header"><div class="flex items-center justify-between"><h2 class="text-xl font-bold text-gray-800">Nómina {{ $run->period }}</h2><a href="{{ route('payroll.index') }}" class="rounded border px-3 py-2 text-xs">⌂ Nómina</a></div></x-slot>
<div class="min-h-screen bg-gray-50 p-5">
    <div class="mx-auto max-w-5xl space-y-5">
        <section class="rounded-xl bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-400">Periodo</p>
                    <h1 class="text-lg font-bold text-gray-800">{{ $run->period }} · {{ ucfirst($run->status) }}</h1>
                    <p class="mt-1 text-xs text-gray-500">Fecha de pago: {{ $run->payment_date->format('d/m/Y') }}</p>
                </div>
                    @if($run->accounting_voucher_id)
                        <a href="{{ route('accounting.vouchers.accounting', $run->accounting_voucher_id) }}" target="_blank" class="rounded border px-3 py-2 text-xs font-bold text-[#227c70]">Ver contabilización</a>
                    @else
                        <span class="rounded border px-3 py-2 text-xs font-bold text-gray-400" title="Esta nómina aún no tiene un asiento contable asociado.">Ver contabilización</span>
                    @endif
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Bruto</p><p class="font-bold text-gray-800">${{ number_format($run->total_gross, 2) }}</p></div>
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Deducciones</p><p class="font-bold text-gray-800">${{ number_format($run->total_deductions, 2) }}</p></div>
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Neto</p><p class="font-bold text-gray-800">${{ number_format($run->total_net, 2) }}</p></div>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-bold text-gray-800">Empleados de este periodo</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-gray-50 text-xs uppercase"><tr><th class="p-2">Empleado</th><th class="p-2">Salario</th><th class="p-2">Deducciones</th><th class="p-2">Neto</th><th class="p-2">Costo empresa</th><th class="p-2">Acciones</th></tr></thead>
                    <tbody>
                        @foreach($run->lines as $line)
                            <tr class="border-b">
                                <td class="p-2">{{ $line->employee?->name ?? 'N/A' }}</td>
                                <td class="p-2">${{ number_format($line->salary, 2) }}</td>
                                <td class="p-2">${{ number_format($line->health_employee + $line->pension_employee + $line->solidarity_employee + $line->withholding, 2) }}</td>
                                <td class="p-2 font-semibold">${{ number_format($line->net_pay, 2) }}</td>
                                <td class="p-2">${{ number_format($line->employer_cost, 2) }}</td>
                                <td class="p-2">
                                    @if($line->employee?->email)
                                        <form method="POST" action="{{ route('payroll.email', $line) }}">@csrf<button class="rounded bg-pink-600 px-2 py-1 text-xs text-white">Enviar desprendible</button></form>
                                    @else
                                        <span class="text-xs text-red-600">Sin correo</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
</x-app-layout>
