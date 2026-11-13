<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Cartera / {{ $voucherTypeLabel }}</p>
                <h2 class="text-xl font-bold text-[#192522]">Causar {{ strtolower($voucherTypeLabel) }}</h2>
            </div>
            <a href="{{ $backUrl }}" class="rounded-lg border border-[#d7dfd8] bg-white px-4 py-2 text-sm font-semibold text-[#192522]">Volver al documento</a>
        </div>
    </x-slot>

    <main class="min-h-screen bg-[#f3f5f1] px-4 py-6 sm:px-8">
        <div class="mx-auto max-w-4xl space-y-5">
            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm sm:p-7">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Tercero</p>
                        <p class="mt-1 font-bold text-[#192522]">{{ $party }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Factura / documento</p>
                        <p class="mt-1 font-bold text-[#192522]">{{ $documentNumber }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Total registrado</p>
                        <p class="mt-1 text-sm font-semibold text-[#192522]">${{ number_format($documentTotal, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#71807a]">Saldo pendiente</p>
                        <p id="payment-balance" data-balance="{{ number_format($balance, 2, '.', '') }}" class="mt-1 text-lg font-black text-[#227c70]">${{ number_format($balance, 2) }}</p>
                    </div>
                </div>
                @if ($originalWithholding > 0)
                    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Este documento ya tiene una retención causada por <strong>${{ number_format($originalWithholding, 2) }}</strong>, incluida en el saldo pendiente. No la repitas aquí. Agrega abajo solo retenciones adicionales aplicadas en este pago.
                    </div>
                @endif
            </section>

            <form method="POST" action="{{ $storeUrl }}" class="space-y-5">
                @csrf
                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm sm:p-7">
                    <h3 class="font-bold text-[#192522]">1. Datos del movimiento</h3>
                    @if ($paymentMethods->isEmpty() || $counterAccounts->isEmpty())
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" role="alert">
                            @if ($paymentMethods->isEmpty())
                                Configura una forma de pago vinculada a una cuenta PUC activa de caja o banco.
                            @endif
                            @if ($counterAccounts->isEmpty())
                                Configura una cuenta auxiliar {{ $isIncome ? '1305 para cartera' : '2205 para proveedores' }} en el PUC.
                            @endif
                        </div>
                    @endif
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-semibold text-[#364640]">
                            Fecha
                            <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required class="rounded-lg border-gray-300 text-sm">
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-[#364640]">
                            Cuenta de caja o banco
                            <select name="payment_method_id" required class="rounded-lg border-gray-300 text-sm">
                                <option value="">Selecciona la cuenta de {{ $isIncome ? 'ingreso' : 'salida' }}</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>
                                        {{ $method->name }} · {{ $method->account?->code }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-[#364640]">
                            Valor que {{ $isIncome ? 'recibiste' : 'saldrá' }} de caja o banco
                            <input id="payment-cash-amount" type="number" step="0.01" min="0.01" max="{{ number_format($balance, 2, '.', '') }}" name="amount" value="{{ old('amount', number_format($balance, 2, '.', '')) }}" required class="rounded-lg border-gray-300 text-sm">
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-[#364640]">
                            Cuenta de {{ $isIncome ? 'cartera del cliente' : 'cuenta por pagar al proveedor' }}
                            <select name="counter_account_id" required class="rounded-lg border-gray-300 text-sm">
                                <option value="">Selecciona la cuenta PUC</option>
                                @foreach ($counterAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('counter_account_id', $defaultCounterAccountId) === (string) $account->id)>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-[#364640]">
                            Referencia bancaria / comprobante de corresponsal
                            <input name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="Obligatoria para pagos por banco" class="rounded-lg border-gray-300 text-sm">
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-[#364640]">
                            Observaciones
                            <input name="notes" maxlength="255" value="{{ old('notes') }}" class="rounded-lg border-gray-300 text-sm">
                        </label>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#d7dfd8] bg-white p-5 shadow-sm sm:p-7">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-[#192522]">2. Retenciones adicionales del pago</h3>
                            <p class="mt-1 text-xs text-[#71807a]">Opcional. En ventas registra lo que el cliente te retuvo; en egresos, lo que retuviste al proveedor.</p>
                        </div>
                        @if ($withholdingAccounts->isNotEmpty())
                            <button id="add-withholding" type="button" class="rounded-lg border border-[#227c70] px-3 py-2 text-xs font-bold text-[#227c70]">＋ Agregar retención</button>
                        @endif
                    </div>
                    @if ($withholdingAccounts->isEmpty())
                        <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">No hay cuentas auxiliares activas para retenciones. Configura una cuenta {{ $isIncome ? '1355' : '2365, 2367 o 2368' }} en el PUC para poder agregar retenciones.</p>
                    @endif
                    <div id="withholding-lines" class="mt-4 grid gap-3">
                        @foreach ($oldWithholdings as $index => $withholding)
                            <div class="withholding-line grid gap-3 rounded-lg border border-[#e5ebe6] p-3 sm:grid-cols-[1fr_180px_auto]">
                                <label class="grid gap-1 text-xs font-semibold text-[#364640]">
                                    Cuenta PUC de retención
                                    <select name="withholdings[{{ $index }}][chart_of_account_id]" class="rounded-lg border-gray-300 text-sm">
                                        <option value="">Selecciona cuenta (opcional)</option>
                                        @foreach ($withholdingAccounts as $account)
                                            <option value="{{ $account->id }}" @selected((string) ($withholding['chart_of_account_id'] ?? '') === (string) $account->id)>
                                                {{ $account->code }} - {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="grid gap-1 text-xs font-semibold text-[#364640]">
                                    Valor retenido
                                    <input type="number" step="0.01" min="0" name="withholdings[{{ $index }}][amount]" value="{{ $withholding['amount'] ?? '' }}" placeholder="0.00" class="withholding-amount rounded-lg border-gray-300 text-sm">
                                </label>
                                <button type="button" class="remove-withholding self-end rounded-lg px-3 py-2 text-sm font-bold text-red-600" aria-label="Quitar retención">Quitar</button>
                            </div>
                        @endforeach
                    </div>
                    <template id="withholding-template">
                        <div class="withholding-line grid gap-3 rounded-lg border border-[#e5ebe6] p-3 sm:grid-cols-[1fr_180px_auto]">
                            <label class="grid gap-1 text-xs font-semibold text-[#364640]">
                                Cuenta PUC de retención
                                <select name="withholdings[__INDEX__][chart_of_account_id]" class="rounded-lg border-gray-300 text-sm">
                                    <option value="">Selecciona cuenta (opcional)</option>
                                    @foreach ($withholdingAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="grid gap-1 text-xs font-semibold text-[#364640]">
                                Valor retenido
                                <input type="number" step="0.01" min="0" name="withholdings[__INDEX__][amount]" placeholder="0.00" class="withholding-amount rounded-lg border-gray-300 text-sm">
                            </label>
                            <button type="button" class="remove-withholding self-end rounded-lg px-3 py-2 text-sm font-bold text-red-600" aria-label="Quitar retención">Quitar</button>
                        </div>
                    </template>
                </section>

                <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-[#b8d9d0] bg-[#edf7f4] p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#47766b]">Total aplicado a la factura</p>
                        <p id="payment-applied-total" class="mt-1 text-xl font-black text-[#192522]">$0.00</p>
                        <p class="mt-1 text-xs text-[#47766b]">Efectivo / banco + retenciones adicionales</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ $backUrl }}" class="rounded-lg border border-[#d7dfd8] bg-white px-4 py-2.5 text-sm font-semibold text-[#192522]">Cancelar</a>
                        <button type="submit" @disabled($paymentMethods->isEmpty() || $counterAccounts->isEmpty()) class="rounded-lg bg-[#227c70] px-5 py-2.5 text-sm font-bold text-white enabled:hover:bg-[#1a5f55] disabled:cursor-not-allowed disabled:opacity-50">Causar {{ strtolower($voucherTypeLabel) }} y registrar pago</button>
                    </div>
                </section>
            </form>
        </div>
    </main>

    <script>
        let withholdingIndex = {{ count($oldWithholdings) }};
        const withholdingLines = document.getElementById('withholding-lines');
        const withholdingTemplate = document.getElementById('withholding-template');
        const cashAmount = document.getElementById('payment-cash-amount');
        const appliedTotal = document.getElementById('payment-applied-total');
        const formatAmount = (amount) => `$${amount.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        function updateAppliedTotal() {
            const retained = [...withholdingLines.querySelectorAll('.withholding-amount')]
                .reduce((sum, input) => sum + (Number(input.value) || 0), 0);
            appliedTotal.textContent = formatAmount((Number(cashAmount.value) || 0) + retained);
        }

        document.getElementById('add-withholding')?.addEventListener('click', () => {
            const line = withholdingTemplate.content.cloneNode(true);
            line.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replaceAll('__INDEX__', String(withholdingIndex));
            });
            withholdingLines.append(line);
            withholdingIndex += 1;
            updateAppliedTotal();
        });

        withholdingLines.addEventListener('click', (event) => {
            const button = event.target.closest('.remove-withholding');
            if (button && withholdingLines.querySelectorAll('.withholding-line').length > 1) {
                button.closest('.withholding-line').remove();
                updateAppliedTotal();
            }
        });
        withholdingLines.addEventListener('input', updateAppliedTotal);
        withholdingLines.addEventListener('change', updateAppliedTotal);
        cashAmount.addEventListener('input', updateAppliedTotal);
        updateAppliedTotal();
    </script>
</x-app-layout>
