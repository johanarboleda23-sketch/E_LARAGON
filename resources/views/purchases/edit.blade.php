<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar compra {{ $purchase->invoice_number }}</title>
    <style>
        body{background:#f3f5f1;font-family:Arial,sans-serif;padding:18px;color:#364640;font-size:12px}
        .form-container{background:white;padding:22px;border-radius:18px;border:1px solid #d7dfd8;box-shadow:0 14px 30px rgba(25,37,34,.07);max-width:1280px;margin:0 auto;box-sizing:border-box}
        .top-bar{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #d7dfd8;padding-bottom:16px;margin-bottom:18px;flex-wrap:wrap;gap:12px}
        .title-pink{color:#192522;font-weight:900;font-size:19px;margin:0;letter-spacing:-.02em}
        .btn-white{background:white;border:1px solid #cbd6cf;color:#364640;padding:7px 11px;border-radius:8px;font-weight:700;font-size:11px;cursor:pointer;text-decoration:none;display:inline-block}
        .btn-pink-light{background:#fff0e8;border:1px solid #f1c2ae;color:#b65338;padding:7px 11px;border-radius:8px;font-weight:800;font-size:11px;cursor:pointer}
        .btn-pink-dark{background:#227c70;color:white;padding:8px 15px;border-radius:8px;font-weight:800;font-size:11px;border:none;cursor:pointer}
        .grid-header{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;background:#f8faf8;padding:14px;border-radius:12px;border:1px solid #d7dfd8;margin-bottom:18px}
        .grid-header label{display:block;font-weight:800;color:#227c70;text-transform:uppercase;font-size:10px;margin-bottom:4px}
        .input-style{width:100%;padding:8px;border-radius:8px;border:1px solid #cbd6cf;box-sizing:border-box;font-size:12px}
        .table-box{border:1px solid #b9ddd3;border-radius:12px;overflow-x:auto;margin-bottom:15px}
        table{width:100%;border-collapse:collapse}
        th{background:#192522;color:#fff;padding:9px;font-size:11px;text-transform:uppercase;text-align:left}
        td{padding:8px;background:white;border-bottom:1px solid #e6ede8}
        .section-label{margin:0 0 8px;color:#71807a;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
        .account-select,.line-description,.asset-fields{display:none}
        .asset-fields{gap:3px}
        .alert{padding:10px;background:#ecfdf5;color:#047857;margin-bottom:12px;border-radius:8px;font-weight:bold;font-size:12px}
        .alert-warn{padding:10px;background:#fff8df;color:#8b6811;margin-bottom:12px;border-radius:8px;font-weight:bold;font-size:12px}
        @media (max-width:800px){.grid-header{grid-template-columns:repeat(2,1fr)}}
        @media (max-width:520px){body{padding:8px}.form-container{padding:14px;border-radius:14px}.grid-header{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="form-container">
        @if(session('success'))<div class="alert">{{ session('success') }}</div>@endif
        <div class="alert-warn">⚠ Al guardar, se revierte el stock y la contabilización anteriores de esta factura y se vuelven a calcular con los datos nuevos.</div>

        <form action="{{ route('purchases.update', $purchase) }}" method="POST" id="purchase-form">
            @csrf
            @method('PUT')

            <div class="top-bar">
                <div class="flex items-center gap-3"><a href="{{ route('purchases.show', $purchase) }}" class="btn-white">⌂ Volver a la factura</a><h2 class="title-pink">✏ Editar compra {{ $purchase->invoice_number }}</h2></div>
                <button type="submit" class="btn-pink-dark">💾 Guardar cambios</button>
            </div>

            <p class="section-label">01 / Datos de la factura</p>
            <div class="grid-header">
                <div><label>N° Factura Proveedor</label><input type="text" name="invoice_number" value="{{ old('invoice_number', $purchase->invoice_number) }}" required class="input-style"></div>
                <div><label>Fecha</label><input type="date" name="purchase_date" value="{{ old('purchase_date', $purchase->purchase_date->toDateString()) }}" required class="input-style"></div>
                <div>
                    <label>Proveedor</label>
                    <select name="provider" required class="input-style" list="suppliers-list">
                        <option value="">Seleccione proveedor</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->name }}" @selected(old('provider', $purchase->provider) === $supplier->name)>{{ $supplier->name }}{{ $supplier->document ? ' - '.$supplier->document : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>NIT / Documento</label><input type="text" name="provider_nit" value="{{ old('provider_nit', $purchase->provider_nit) }}" class="input-style"></div>
                <div>
                    <label>Forma de pago</label>
                    <select name="payment_method_id" class="input-style">
                        <option value="">Sin forma de pago</option>
                        @foreach($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->id }}" @selected(old('payment_method_id', $purchase->payment_method_id) == $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Responsabilidad Fiscal</label>
                    <select name="provider_regimen" id="provider-regimen" onchange="calculateTotals()" required class="input-style">
                        <option value="comun">Régimen Común</option>
                        <option value="simplificado">Régimen Simplificado</option>
                        <option value="gran_contribuyente">Gran Contribuyente</option>
                        <option value="sin_responsabilidad">SIN RESPONSABILIDAD</option>
                    </select>
                </div>
                <div>
                    <label>Concepto de retención</label>
                    <select name="withholding_concept" id="withholding-concept" onchange="calculateTotals()" class="input-style">
                        @foreach($withholdings['concepts'] as $conceptKey => $concept)
                            <option value="{{ $conceptKey }}" data-rate="{{ $concept['rate'] }}" data-base-uvt="{{ $concept['base_uvt'] }}" data-base-on="{{ $concept['base_on'] }}">{{ $concept['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="section-label">02 / Detalle y clasificación</p>
            <div class="table-box">
                <table id="items-table">
                    <thead>
                        <tr>
                            <th style="width:13%;">Sección</th>
                            <th style="width:25%;">Producto o cuenta PUC</th>
                            <th style="text-align:center;width:10%;">Cant.</th>
                            <th style="text-align:right;width:15%;">Costo ($)</th>
                            <th style="text-align:center;width:12%;">IVA</th>
                            <th style="text-align:center;width:12%;">Ganancia (%)</th>
                            <th style="width:20%;">Datos de activo fijo</th>
                            <th style="text-align:right;width:10%;">Total ($)</th>
                            <th style="width:3%;"></th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        @foreach($purchase->details as $index => $detail)
                        <tr class="item-row">
                            <td>
                                <select name="items[{{ $index }}][purchase_line_type]" class="input-style line-type" required>
                                    <option value="producto" @selected($detail->purchase_line_type === 'producto')>Producto</option>
                                    <option value="gasto" @selected($detail->purchase_line_type === 'gasto')>Gasto</option>
                                    <option value="activo_fijo" @selected($detail->purchase_line_type === 'activo_fijo')>Activo fijo</option>
                                </select>
                            </td>
                            <td class="line-target">
                                <select name="items[{{ $index }}][item_id]" class="input-style product-select">
                                    <option value="">Seleccione producto</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" @selected($detail->item_id === $product->id)>{{ $product->name }}{{ $product->code ? ' - '.$product->code : '' }}</option>
                                    @endforeach
                                </select>
                                <select name="items[{{ $index }}][chart_of_account_id]" class="input-style account-select">
                                    <option value="">Seleccione cuenta PUC</option>
                                    @foreach($postingAccounts as $account)
                                        <option value="{{ $account->id }}" @selected($detail->chart_of_account_id === $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="items[{{ $index }}][line_description]" class="input-style line-description" placeholder="Descripción del gasto" value="{{ $detail->line_description }}">
                            </td>
                            <td><input type="number" name="items[{{ $index }}][quantity]" class="qty-input input-style" style="text-align:center;" value="{{ $detail->quantity }}" min="1" required></td>
                            <td><input type="number" step="0.01" name="items[{{ $index }}][cost_price]" class="price-input input-style" style="text-align:right;" value="{{ $detail->cost_price }}" required></td>
                            <td><select name="items[{{ $index }}][iva_percentage]" class="iva-input input-style" style="text-align:center;"><option value="19" @selected((float)$detail->iva_percentage === 19.0)>19%</option><option value="5" @selected((float)$detail->iva_percentage === 5.0)>5%</option><option value="0" @selected((float)$detail->iva_percentage === 0.0)>0%</option></select></td>
                            <td><input type="number" name="items[{{ $index }}][utility_percentage]" class="utility-input input-style" style="text-align:center;" min="0" step="0.01" value="{{ $detail->utility_percentage }}"></td>
                            <td class="asset-fields">
                                <input type="number" name="items[{{ $index }}][useful_life_months]" class="input-style useful-life" min="1" placeholder="Vida útil (meses)" value="{{ $detail->useful_life_months }}">
                                <input type="number" name="items[{{ $index }}][residual_value]" class="input-style residual-value" min="0" step="0.01" placeholder="Valor residual" value="{{ $detail->residual_value }}">
                                <select name="items[{{ $index }}][depreciation_expense_account_id]" class="input-style depreciation-expense-account">
                                    <option value="">Gasto depreciación</option>
                                    @foreach($postingAccounts as $account)
                                        <option value="{{ $account->id }}" @selected($detail->depreciation_expense_account_id === $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <select name="items[{{ $index }}][accumulated_depreciation_account_id]" class="input-style accumulated-depreciation-account">
                                    <option value="">Depreciación acumulada</option>
                                    @foreach($postingAccounts as $account)
                                        <option value="{{ $account->id }}" @selected($detail->accumulated_depreciation_account_id === $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="items[{{ $index }}][depreciation_method]" value="straight_line">
                            </td>
                            <td class="row-total" style="text-align:right;font-weight:bold;">$0.00</td>
                            <td style="text-align:center;"><button type="button" onclick="removeRow(this)" style="color:#f87171;background:none;border:none;font-size:16px;font-weight:bold;cursor:pointer;">&times;</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" onclick="addRow()" class="btn-pink-light" style="margin-bottom:15px;">➕ Agregar Renglón</button>

            <div style="background:#fff8df;padding:15px;border-radius:12px;border:1px solid #f0d98b;max-width:320px;margin-left:auto;">
                <div style="display:flex;justify-content:space-between;margin:6px 0;"><span>Subtotal</span><strong id="subtotal-total">$0.00</strong></div>
                <div style="display:flex;justify-content:space-between;margin:6px 0;"><span>IVA</span><strong id="iva-total">$0.00</strong></div>
                <div style="display:flex;justify-content:space-between;margin:6px 0;"><span id="retention-label">Retención</span><strong id="retention-total">$0.00</strong></div>
                <div style="border-top:1px solid #e7c96b;padding-top:8px;margin-top:8px;font-size:14px;font-weight:900;color:#8b6811;display:flex;justify-content:space-between;"><span>Total</span><strong id="grand-total">$0.00</strong></div>
            </div>
        </form>
    </div>

    <script>
        let rowIndex = {{ $purchase->details->count() }};
        const productsOptions = `@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}{{ $product->code ? ' - '.$product->code : '' }}</option>@endforeach`;
        const accountsOptions = `@foreach($postingAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach`;

        function syncLineFields(row) {
            const lineType = row.querySelector('.line-type').value;
            const productSelect = row.querySelector('.product-select');
            const accountSelect = row.querySelector('.account-select');
            const description = row.querySelector('.line-description');
            const utility = row.querySelector('.utility-input');
            const assetFields = row.querySelector('.asset-fields');

            productSelect.style.display = lineType === 'producto' ? 'block' : 'none';
            productSelect.required = lineType === 'producto';
            accountSelect.style.display = lineType === 'producto' ? 'none' : 'block';
            accountSelect.required = lineType !== 'producto';
            description.style.display = lineType === 'gasto' ? 'block' : 'none';
            assetFields.style.display = lineType === 'activo_fijo' ? 'grid' : 'none';
        }

        function addRow() {
            const tableBody = document.getElementById('table-body');
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.innerHTML = `
                <td><select name="items[${rowIndex}][purchase_line_type]" class="input-style line-type" required><option value="producto">Producto</option><option value="gasto">Gasto</option><option value="activo_fijo">Activo fijo</option></select></td>
                <td class="line-target"><select name="items[${rowIndex}][item_id]" class="input-style product-select"><option value="">Seleccione producto</option>${productsOptions}</select><select name="items[${rowIndex}][chart_of_account_id]" class="input-style account-select"><option value="">Seleccione cuenta PUC</option>${accountsOptions}</select><input type="text" name="items[${rowIndex}][line_description]" class="input-style line-description" placeholder="Descripción del gasto"></td>
                <td><input type="number" name="items[${rowIndex}][quantity]" class="qty-input input-style" style="text-align:center;" value="1" min="1" required></td>
                <td><input type="number" step="0.01" name="items[${rowIndex}][cost_price]" class="price-input input-style" style="text-align:right;" placeholder="0.00" required></td>
                <td><select name="items[${rowIndex}][iva_percentage]" class="iva-input input-style" style="text-align:center;"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td>
                <td><input type="number" name="items[${rowIndex}][utility_percentage]" class="utility-input input-style" style="text-align:center;" min="0" step="0.01" value="0"></td>
                <td class="asset-fields"><input type="number" name="items[${rowIndex}][useful_life_months]" class="input-style useful-life" min="1" placeholder="Vida útil (meses)"><input type="number" name="items[${rowIndex}][residual_value]" class="input-style residual-value" min="0" step="0.01" placeholder="Valor residual"><select name="items[${rowIndex}][depreciation_expense_account_id]" class="input-style depreciation-expense-account"><option value="">Gasto depreciación</option>${accountsOptions}</select><select name="items[${rowIndex}][accumulated_depreciation_account_id]" class="input-style accumulated-depreciation-account"><option value="">Depreciación acumulada</option>${accountsOptions}</select><input type="hidden" name="items[${rowIndex}][depreciation_method]" value="straight_line"></td>
                <td class="row-total" style="text-align:right;font-weight:bold;">$0.00</td>
                <td style="text-align:center;"><button type="button" onclick="removeRow(this)" style="color:#f87171;background:none;border:none;font-size:16px;font-weight:bold;cursor:pointer;">&times;</button></td>
            `;
            tableBody.appendChild(row);
            syncLineFields(row);
            rowIndex += 1;
            calculateTotals();
        }

        function removeRow(button) {
            const rows = document.querySelectorAll('.item-row');
            if (rows.length > 1) button.closest('.item-row').remove();
            calculateTotals();
        }

        function calculateTotals() {
            let subtotal = 0;
            let ivaTotal = 0;
            const providerRegimen = document.getElementById('provider-regimen').value;
            const withholdingSelect = document.getElementById('withholding-concept');
            if (providerRegimen === 'sin_responsabilidad' && withholdingSelect.value === 'none') {
                withholdingSelect.value = 'purchase_no_declarante';
            }
            const selectedConcept = withholdingSelect.options[withholdingSelect.selectedIndex];
            const retentionRate = Number(selectedConcept?.dataset.rate || 0);
            const baseUvt = Number(selectedConcept?.dataset.baseUvt || 0);
            const baseOn = selectedConcept?.dataset.baseOn || 'subtotal';

            document.querySelectorAll('.item-row').forEach((row) => {
                const quantity = Number(row.querySelector('.qty-input')?.value || 0);
                const price = Number(row.querySelector('.price-input')?.value || 0);
                const ivaPercentage = Number(row.querySelector('.iva-input')?.value || 0);
                const lineSubtotal = quantity * price;
                const lineIva = lineSubtotal * ivaPercentage / 100;

                subtotal += lineSubtotal;
                ivaTotal += lineIva;
                row.querySelector('.row-total').textContent = formatCurrency(lineSubtotal + lineIva);
            });

            const retentionBase = baseOn === 'iva' ? ivaTotal : subtotal;
            const minimumBase = baseUvt * {{ $withholdings['uvt'] }};
            const taxableBase = retentionBase >= minimumBase ? retentionBase : 0;
            const retention = taxableBase * retentionRate;
            const total = subtotal + ivaTotal - retention;
            document.getElementById('subtotal-total').textContent = formatCurrency(subtotal);
            document.getElementById('iva-total').textContent = formatCurrency(ivaTotal);
            document.getElementById('retention-label').textContent = selectedConcept?.textContent || 'Retención';
            document.getElementById('retention-total').textContent = formatCurrency(retention);
            document.getElementById('grand-total').textContent = formatCurrency(total);
        }

        function formatCurrency(value) {
            return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        document.addEventListener('input', (event) => {
            if (event.target.closest('.item-row')) calculateTotals();
        });

        document.addEventListener('change', (event) => {
            const row = event.target.closest('.item-row');
            if (!row) return;
            if (event.target.matches('.line-type')) syncLineFields(row);
            calculateTotals();
        });

        document.querySelectorAll('.item-row').forEach(syncLineFields);
        calculateTotals();
    </script>
</body>
</html>
