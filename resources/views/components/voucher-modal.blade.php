<div id="voucher-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" onclick="if(event.target===this) closeVoucherModal()">
    <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
        <div class="mb-3 flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-pink-500">Asiento contable (partida doble)</p>
                <h3 id="voucher-modal-title" class="text-lg font-bold text-gray-800"></h3>
                <p id="voucher-modal-subtitle" class="text-xs text-gray-500"></p>
            </div>
            <button type="button" onclick="closeVoucherModal()" class="rounded-md border border-gray-200 px-2 py-1 text-sm text-gray-500 hover:bg-gray-50">✕</button>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs uppercase text-gray-500">
                    <th class="py-2">Cuenta PUC</th>
                    <th class="py-2">Detalle</th>
                    <th class="py-2 text-right">Débito</th>
                    <th class="py-2 text-right">Crédito</th>
                </tr>
            </thead>
            <tbody id="voucher-modal-lines"></tbody>
            <tfoot>
                <tr class="border-t font-bold">
                    <td class="py-2" colspan="2">Totales</td>
                    <td id="voucher-modal-total-debit" class="py-2 text-right"></td>
                    <td id="voucher-modal-total-credit" class="py-2 text-right"></td>
                </tr>
            </tfoot>
        </table>
        <p id="voucher-modal-error" class="mt-3 hidden text-sm text-red-600"></p>
    </div>
</div>

<script>
    function voucherModalFmt(n) { return (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function openVoucherModal(voucherId) {
        if (!voucherId) return;
        const modal = document.getElementById('voucher-modal');
        const title = document.getElementById('voucher-modal-title');
        const subtitle = document.getElementById('voucher-modal-subtitle');
        const linesBody = document.getElementById('voucher-modal-lines');
        const totalDebit = document.getElementById('voucher-modal-total-debit');
        const totalCredit = document.getElementById('voucher-modal-total-credit');
        const errorBox = document.getElementById('voucher-modal-error');

        title.textContent = 'Cargando...';
        subtitle.textContent = '';
        linesBody.innerHTML = '';
        totalDebit.textContent = '';
        totalCredit.textContent = '';
        errorBox.classList.add('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        fetch(`/comprobantes/${voucherId}/json`)
            .then((response) => { if (!response.ok) throw new Error('No se pudo cargar el comprobante.'); return response.json(); })
            .then((data) => {
                title.textContent = `Comprobante ${data.consecutive}`;
                subtitle.textContent = `${data.voucher_type.toUpperCase()} · ${data.voucher_date}${data.third_party ? ' · ' + data.third_party : ''}`;
                linesBody.innerHTML = data.lines.map((line) => `<tr class="border-b"><td class="py-2">${line.account}</td><td class="py-2">${line.detail ?? ''}</td><td class="py-2 text-right">${line.debit ? '$' + voucherModalFmt(line.debit) : ''}</td><td class="py-2 text-right">${line.credit ? '$' + voucherModalFmt(line.credit) : ''}</td></tr>`).join('');
                totalDebit.textContent = '$' + voucherModalFmt(data.total_debit);
                totalCredit.textContent = '$' + voucherModalFmt(data.total_credit);
            })
            .catch((error) => {
                title.textContent = 'Asiento contable';
                errorBox.textContent = error.message;
                errorBox.classList.remove('hidden');
            });
    }

    function closeVoucherModal() {
        const modal = document.getElementById('voucher-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
</script>
