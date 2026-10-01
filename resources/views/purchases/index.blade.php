<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Gestión de Compras Avanzado</title>
    <!-- ESTILOS COMPACTOS LOCALES BLINDADOS PARA MONITORES HP -->
    <style>
        body { background-color: #fdf2f8; font-family: sans-serif; padding: 10px; font-size: 12px; color: #374151; }
        .form-container { background: white; padding: 20px; border-radius: 12px; border: 1px solid #fbcfe8; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); max-w: 100%; box-sizing: border-box; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #fbcfe8; padding-bottom: 10px; margin-bottom: 15px; flex-wrap: wrap; gap: 8px; }
        .title-pink { color: #be185d; font-weight: bold; font-size: 16px; margin: 0; }
        .btn-group { display: flex; gap: 4px; background: #f9fafb; padding: 6px; border-radius: 8px; border: 1px solid #e5e7eb; flex-wrap: wrap; }
        .btn-white { background: white; border: 1px solid #d1d5db; padding: 5px 10px; border-radius: 6px; font-weight: 600; font-size: 11px; cursor: pointer; }
        .btn-pink-light { background: #fce7f3; border: 1px solid #fbcfe8; color: #be185d; padding: 5px 10px; border-radius: 6px; font-weight: bold; font-size: 11px; cursor: pointer; }
        .btn-pink-dark { background: #db2777; color: white; padding: 5px 14px; border-radius: 6px; font-weight: bold; font-size: 11px; border: none; cursor: pointer; }
        .download-menu { position: relative; }
        .download-menu > button { display: inline-flex; align-items: center; gap: 5px; }
        .download-options { display: none; position: absolute; right: 0; top: calc(100% + 4px); z-index: 10; min-width: 130px; padding: 4px; background: white; border: 1px solid #fbcfe8; border-radius: 6px; box-shadow: 0 8px 18px rgba(190,24,93,0.15); }
        .download-menu.open .download-options { display: grid; }
        .download-options button { border: 0; background: white; padding: 7px 9px; text-align: left; color: #374151; cursor: pointer; font-size: 11px; }
        .download-options button:hover { background: #fce7f3; color: #be185d; }
        @media print {
            body { background: white; padding: 0; }
            .top-bar, .dian-box, .btn-pink-light, .download-menu, .btn-pink-dark, .remove-row, #payment-method, #credit-days { display: none !important; }
            .form-container { border: 0; box-shadow: none; padding: 0; }
        }
        .dian-box { background: linear-gradient(to right, rgba(219,39,119,0.1), rgba(147,51,234,0.05)); padding: 10px; border-radius: 10px; border: 1px solid #fbcfe8; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .grid-header { display: grid; grid-template-cols: repeat(5, 1fr); gap: 10px; background: rgba(252,231,243,0.3); padding: 10px; border-radius: 10px; border: 1px solid #fbcfe8; margin-bottom: 15px; }
        .grid-header label { display: block; font-weight: bold; color: #be185d; text-transform: uppercase; font-size: 10px; margin-bottom: 2px; }
        .input-style { width: 100%; padding: 6px; border-radius: 6px; border: 1px solid #fbcfe8; box-sizing: border-box; font-size: 12px; }
        .table-box { border: 1px solid #db2777; border-radius: 10px; overflow-x: auto; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #db2777; color: white; padding: 8px; font-size: 11px; text-transform: uppercase; text-align: left; }
        td { padding: 8px; background: white; border-bottom: 1px solid #fce7f3; }
        .payment-box { background: #f9fafb; padding: 15px; border-radius: 10px; border: 1px solid #e5e7eb; box-sizing: border-box; margin-top: 15px; }
        .totals-box { background: rgba(252,231,243,0.2); padding: 15px; border-radius: 10px; border: 1px solid #fbcfe8; font-weight: 500; box-sizing: border-box; margin-top: 15px; }
        .total-row-pink { border-top: 1px solid #fbcfe8; padding-top: 8px; margin-top: 8px; font-size: 14px; font-weight: 900; color: #be185d; display: flex; justify-content: space-between; }
        .flex-box { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .account-select, .line-description, .asset-fields { display: none; }
        .asset-fields { gap: 3px; }
    </style>
</head>
<body>

    <div class="form-container">
        <!-- FORMULARIO GENERAL DE COMPRAS -->
        <form action="{{ route('purchases.store') }}" method="POST" id="purchase-form">
            @csrf

            <!-- BARRA SUPERIOR PROFESIONAL -->
            <div class="top-bar">
                <div class="flex items-center gap-3"><a href="{{ route('dashboard') }}" class="btn-white" style="text-decoration:none;">⌂ Tablero</a><h2 class="title-pink">🛒 Módulo de Gestión de Compras Avanzado</h2></div>
                <div class="btn-group">
                    <button type="button" onclick="editInvoice()" class="btn-white">Editar</button>
                    <button type="button" onclick="clearInvoice()" class="btn-white">Eliminar</button>
                    <button type="button" onclick="showCreditNote()" class="btn-pink-light">Notas Crédito / Débito</button>
                    <button type="button" onclick="focusReceivable()" class="btn-white" style="border-color:#bfdbfe; color:#1d4ed8;">Estado Cartera (C x P)</button>
                    <button type="button" onclick="showAccountingEntry()" class="btn-white" style="border-color:#e9d5ff; color:#6b21a8;">Ver Asiento Contable</button>
                    <a href="/items" class="btn-pink-light" style="text-decoration:none; display:inline-block; line-height:14px;">📦 Almacén / Inventario</a>
                    <div class="download-menu" id="download-menu">
                        <button type="button" class="btn-white" onclick="toggleDownloadMenu(event)" aria-expanded="false" title="Imprimir o descargar">⇩ <span>Salida</span></button>
                        <div class="download-options" role="menu">
                            <button type="button" onclick="printInvoice()" role="menuitem">🖨 Imprimir</button>
                            <button type="button" onclick="downloadInvoice()" role="menuitem">⇩ Descargar</button>
                        </div>
                    </div>
                    <button type="submit" class="btn-pink-dark">💾 Guardar Factura</button>
                </div>
            </div>

            <!-- CARGADOR ROBÓTICO XML DIAN -->
            <div class="dian-box" id="dian">
                <div>
                    <h4 style="color:#9d174d; font-weight:bold; margin:0 0 2px 0; text-transform: uppercase; font-size:11px;">⚡ Causación Automática IA + DIAN</h4>
                    <p style="color:#6b7280; font-size:10px; margin:0;">Sube el archivo XML de tu proveedor para rellenar NIT, Factura y Totales mediante el código CUFE.</p>
                </div>
                <div>
                    <input type="file" id="xml_file" accept=".xml" onchange="processDIANXml()" style="display:none;">
                    <button type="button" onclick="openDianPortal()" class="btn-white" style="border-color:#059669; color:#047857; padding:4px 10px;">↗ Ir a DIAN</button>
                    <button type="button" onclick="document.getElementById('xml_file').click()" class="btn-pink-dark" style="background:#059669; padding:4px 10px;">⚡ Cargar XML DIAN</button>
                    <span id="upload-status" style="margin-left:10px; font-style:italic; font-weight:bold;"></span>
                </div>
            </div>

            <input type="hidden" name="cufe_dian" id="hidden-cufe">
            <input type="hidden" name="provider_prefix" id="provider-prefix">
            <input type="hidden" name="provider_consecutive" id="provider-consecutive">

            <!-- ENCABEZADO FISCAL ESTILO FACTURA REAL -->
            <div class="grid-header">
                <div>
                    <label>Tipo Documento</label>
                    <select name="document_type_id" class="input-style"><option value="contado">Factura Contado (FCC)</option><option value="credito">Factura Crédito (FCR)</option></select>
                </div>
                <div><label>Consecutivo Interno</label><input type="text" name="consecutivo" id="consecutivo" value="COM-001" class="input-style" style="background:#f3f4f6; font-weight:bold;" maxlength="50"></div>
                <div><label>N° Factura Proveedor</label><input type="text" id="invoice_number" name="invoice_number" required class="input-style"></div>
                <div><label>Proveedor / NIT</label><select id="provider" name="provider" required class="input-style"><option value="">Seleccione proveedor</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->name }}">{{ $supplier->name }}{{ $supplier->document ? ' - ' . $supplier->document : '' }}</option>@endforeach</select></div>
                <div>
                    <label>Responsabilidad Fiscal</label>
                    <select name="provider_regimen" id="provider-regimen" onchange="calculateTotals()" required class="input-style"><option value="comun">Régimen Común</option><option value="simplificado">Régimen Simplificado</option><option value="gran_contribuyente">Gran Contribuyente</option><option value="sin_responsabilidad">SIN RESPONSABILIDAD</option></select>
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

            <!-- TABLA MULTI-RENGLÓN COMPACTA -->
            <div class="table-box">
                <table id="items-table">
                    <thead>
                        <tr>
                            <th style="width:13%;">Sección</th>
                            <th style="width:25%;">Producto o cuenta PUC</th>
                            <th style="text-align:center; width:10%;">Cant.</th>
                            <th style="text-align:right; width:15%;">Costo ($)</th>
                            <th style="text-align:center; width:12%;">IVA</th>
                            <th style="text-align:center; width:12%;">Ganancia (%)</th>
                            <th style="width:20%;">Datos de activo fijo</th>
                            <th style="text-align:right; width:10%;">Total ($)</th>
                            <th style="width:3%;"></th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr class="item-row">
                            <td>
                                <select name="items[0][purchase_line_type]" class="input-style line-type" required>
                                    <option value="producto">Producto</option>
                                    <option value="gasto">Gasto</option>
                                    <option value="activo_fijo">Activo fijo</option>
                                </select>
                            </td>
                            <td class="line-target">
                                <select name="items[0][item_id]" class="input-style product-select" required>
                                    <option value="">Seleccione producto</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}{{ $product->code ? ' - '.$product->code : '' }}</option>
                                    @endforeach
                                </select>
                                <select name="items[0][chart_of_account_id]" class="input-style account-select" disabled>
                                    <option value="">Seleccione cuenta PUC</option>
                                    @foreach($postingAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="items[0][line_description]" class="input-style line-description" placeholder="Descripción del gasto" disabled>
                            </td>
                            <td><input type="number" name="items[0][quantity]" class="qty-input input-style" style="text-align:center;" value="1" min="1" required></td>
                            <td><input type="number" step="0.01" name="items[0][cost_price]" class="price-input input-style" style="text-align:right;" placeholder="0.00" required></td>
                            <td><select name="items[0][iva_percentage]" class="iva-input input-style" style="text-align:center;"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td>
                            <td><input type="number" name="items[0][utility_percentage]" class="utility-input input-style" style="text-align:center;" min="0" step="0.01" value="0"></td>
                            <td class="asset-fields">
                                <input type="number" name="items[0][useful_life_months]" class="input-style useful-life" min="1" placeholder="Vida útil (meses)" disabled>
                                <input type="number" name="items[0][residual_value]" class="input-style residual-value" min="0" step="0.01" placeholder="Valor residual" disabled>
                                <select name="items[0][depreciation_expense_account_id]" class="input-style depreciation-expense-account" disabled>
                                    <option value="">Gasto depreciación</option>
                                    @foreach($postingAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <select name="items[0][accumulated_depreciation_account_id]" class="input-style accumulated-depreciation-account" disabled>
                                    <option value="">Depreciación acumulada</option>
                                    @foreach($postingAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="items[0][depreciation_method]" value="straight_line" class="depreciation-method">
                            </td>
                            <td class="row-total" style="text-align:right; font-weight:bold;">$0.00</td>
                            <td style="text-align:center;"><button type="button" onclick="removeRow(this)" style="color:#f87171; background:none; border:none; font-size:16px; font-weight:bold; cursor:pointer;">&times;</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button type="button" onclick="addRow()" class="btn-pink-light" style="margin-bottom:15px;">➕ Agregar Renglón</button>

            <!-- SECCIÓN INFERIOR COMPACTADA AL 100% -->
            <div style="display: grid; grid-template-cols: 1.8fr 1fr; gap: 15px; align-items: start;">
                
                <!-- PASARELA MULTICUENTA -->
                <div class="payment-box">
                    <h3 style="margin:0 0 10px; color:#be185d; font-size:13px;">Forma de pago</h3>
                    <label for="payment-method">Seleccione cómo se pagará la factura</label>
                    <select name="payment_method_id" id="payment-method" class="input-style" required>
                        <option value="">Seleccione una forma de pago</option>
                        @foreach($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->id }}">{{ $paymentMethod->name }}</option>
                        @endforeach
                    </select>
                    <label for="credit-days" style="display:block; margin-top:10px;">Días de crédito</label>
                    <input type="number" name="credit_days" id="credit-days" class="input-style" min="0" value="0">
                </div>

                <div class="totals-box">
                    <div class="flex-box"><span>Subtotal</span><strong id="subtotal-total">$0.00</strong></div>
                    <div class="flex-box"><span>IVA</span><strong id="iva-total">$0.00</strong></div>
                    <div class="flex-box"><span id="retention-label">Retención</span><strong id="retention-total">$0.00</strong></div>
                    <div class="total-row-pink"><span>Total</span><strong id="grand-total">$0.00</strong></div>
                </div>
            </div>

            <input type="hidden" name="subtotal" id="subtotal-value" value="0">
            <input type="hidden" name="iva_total" id="iva-value" value="0">
            <input type="hidden" name="retefuente" id="retention-value" value="0">
            <input type="hidden" name="retention_base" id="retention-base-value" value="0">
            <input type="hidden" name="total_pagar" id="total-value" value="0">
        </form>
    </div>

    <div class="form-container" style="margin-top:15px;">
        <h3 class="title-pink" style="margin-bottom:10px;">Historial de facturas y responsables</h3>
        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Proveedor</th>
                        <th>Creada por</th>
                        <th>Fecha creación</th>
                        <th>Eliminada por</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPurchases as $purchase)
                        <tr>
                            <td>{{ $purchase->invoice_number }}</td>
                            <td>{{ $purchase->provider }}</td>
                            <td>{{ $purchase->creator?->name ?? 'Sistema / invitado' }}</td>
                            <td>{{ $purchase->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $purchase->deleter?->name ?? 'N/A' }}</td>
                            <td>{{ $purchase->trashed() ? 'Eliminada' : 'Activa' }}</td>
                            <td>
                                @if(!$purchase->trashed())
                                    <form action="{{ route('purchases.destroy', $purchase) }}" method="POST" onsubmit="return confirm('¿Eliminar esta factura? Quedará en el historial.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-white">Eliminar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aún no hay facturas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let rowIndex = 1;

        function syncLineFields(row) {
            const lineType = row.querySelector('.line-type').value;
            const productSelect = row.querySelector('.product-select');
            const accountSelect = row.querySelector('.account-select');
            const description = row.querySelector('.line-description');
            const utility = row.querySelector('.utility-input');
            const assetFields = row.querySelector('.asset-fields');

            productSelect.style.display = lineType === 'producto' ? 'block' : 'none';
            productSelect.disabled = lineType !== 'producto';
            productSelect.required = lineType === 'producto';
            accountSelect.style.display = lineType === 'producto' ? 'none' : 'block';
            accountSelect.disabled = lineType === 'producto';
            accountSelect.required = lineType !== 'producto';
            description.style.display = lineType === 'gasto' ? 'block' : 'none';
            description.disabled = lineType !== 'gasto';
            utility.disabled = lineType !== 'producto';
            assetFields.style.display = lineType === 'activo_fijo' ? 'grid' : 'none';
            assetFields.querySelectorAll('input, select').forEach((field) => {
                if (!field.classList.contains('depreciation-method')) field.disabled = lineType !== 'activo_fijo';
            });
        }

        function addRow() {
            const tableBody = document.getElementById('table-body');
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.innerHTML = `
                <td><select name="items[${rowIndex}][purchase_line_type]" class="input-style line-type" required><option value="producto">Producto</option><option value="gasto">Gasto</option><option value="activo_fijo">Activo fijo</option></select></td>
                <td class="line-target"><select name="items[${rowIndex}][item_id]" class="input-style product-select" required><option value="">Seleccione producto</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}{{ $product->code ? ' - '.$product->code : '' }}</option>@endforeach</select><select name="items[${rowIndex}][chart_of_account_id]" class="input-style account-select" disabled><option value="">Seleccione cuenta PUC</option>@foreach($postingAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select><input type="text" name="items[${rowIndex}][line_description]" class="input-style line-description" placeholder="Descripción del gasto" disabled></td>
                <td><input type="number" name="items[${rowIndex}][quantity]" class="qty-input input-style" style="text-align:center;" value="1" min="1" required></td>
                <td><input type="number" step="0.01" name="items[${rowIndex}][cost_price]" class="price-input input-style" style="text-align:right;" placeholder="0.00" required></td>
                <td><select name="items[${rowIndex}][iva_percentage]" class="iva-input input-style" style="text-align:center;"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td>
                <td><input type="number" name="items[${rowIndex}][utility_percentage]" class="utility-input input-style" style="text-align:center;" min="0" step="0.01" value="0"></td>
                <td class="asset-fields"><input type="number" name="items[${rowIndex}][useful_life_months]" class="input-style useful-life" min="1" placeholder="Vida útil (meses)" disabled><input type="number" name="items[${rowIndex}][residual_value]" class="input-style residual-value" min="0" step="0.01" placeholder="Valor residual" disabled><select name="items[${rowIndex}][depreciation_expense_account_id]" class="input-style depreciation-expense-account" disabled><option value="">Gasto depreciación</option>@foreach($postingAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select><select name="items[${rowIndex}][accumulated_depreciation_account_id]" class="input-style accumulated-depreciation-account" disabled><option value="">Depreciación acumulada</option>@foreach($postingAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select><input type="hidden" name="items[${rowIndex}][depreciation_method]" value="straight_line" class="depreciation-method"></td>
                <td class="row-total" style="text-align:right; font-weight:bold;">$0.00</td>
                <td style="text-align:center;"><button type="button" onclick="removeRow(this)" style="color:#f87171; background:none; border:none; font-size:16px; font-weight:bold; cursor:pointer;">&times;</button></td>
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
            document.getElementById('subtotal-value').value = subtotal.toFixed(2);
            document.getElementById('iva-value').value = ivaTotal.toFixed(2);
            document.getElementById('retention-value').value = retention.toFixed(2);
            document.getElementById('retention-base-value').value = taxableBase.toFixed(2);
            document.getElementById('total-value').value = total.toFixed(2);
        }

        function formatCurrency(value) {
            return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function openDianPortal() {
            window.open('https://www.dian.gov.co/', '_blank', 'noopener,noreferrer');
        }

        function editInvoice() {
            document.querySelectorAll('#purchase-form input, #purchase-form select').forEach((field) => {
                if (field.type !== 'hidden') field.disabled = false;
            });
            document.getElementById('invoice_number').focus();
        }

        function clearInvoice() {
            if (!window.confirm('¿Deseas limpiar los datos de esta factura?')) return;
            document.getElementById('purchase-form').reset();
            document.querySelectorAll('.item-row:not(:first-child)').forEach((row) => row.remove());
            rowIndex = 1;
            calculateTotals();
        }

        function showCreditNote() {
            window.alert('Las notas crédito y débito se gestionarán desde el documento guardado.');
        }

        function focusReceivable() {
            const paymentMethod = document.getElementById('payment-method');
            paymentMethod.value = 'credit';
            document.getElementById('credit-days').focus();
        }

        function showAccountingEntry() {
            const total = document.getElementById('total-value').value;
            window.alert('Asiento contable preliminar\n\nDébito: Inventario\nCrédito: Proveedores\nTotal: ' + formatCurrency(Number(total)));
        }

        function toggleDownloadMenu(event) {
            event.stopPropagation();
            const menu = document.getElementById('download-menu');
            menu.classList.toggle('open');
            menu.querySelector('button').setAttribute('aria-expanded', menu.classList.contains('open'));
        }

        function printInvoice() {
            document.getElementById('download-menu').classList.remove('open');
            window.print();
        }

        function downloadInvoice() {
            const rows = Array.from(document.querySelectorAll('.item-row')).map((row) => {
                const product = row.querySelector('.product-search').value;
                const quantity = row.querySelector('.qty-input').value;
                const price = row.querySelector('.price-input').value;
                const iva = row.querySelector('.iva-input').value;
                return [product, quantity, price, iva, row.querySelector('.row-total').textContent];
            });
            const csv = [
                ['Factura', document.getElementById('invoice_number').value],
                ['Proveedor', document.getElementById('provider').value],
                [],
                ['Producto', 'Cantidad', 'Costo', 'IVA %', 'Total'],
                ...rows,
                [],
                ['Subtotal', document.getElementById('subtotal-total').textContent],
                ['IVA', document.getElementById('iva-total').textContent],
                ['Total', document.getElementById('grand-total').textContent]
            ].map((line) => line.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(';')).join('\n');
            const blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = (document.getElementById('invoice_number').value || 'factura-compra') + '.csv';
            link.click();
            URL.revokeObjectURL(link.href);
            document.getElementById('download-menu').classList.remove('open');
        }

        function processDIANXml() {
            const fileInput = document.getElementById('xml_file');
            const status = document.getElementById('upload-status');
            if (!fileInput.files.length) return;

            const formData = new FormData();
            formData.append('xml_file', fileInput.files[0]);
            status.textContent = 'Procesando...';

            fetch('{{ route('purchases.import-xml') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value },
                body: formData
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success) throw new Error(data.message);
                    document.getElementById('invoice_number').value = data.invoice_number;
                    document.getElementById('provider').value = data.provider;
                    document.getElementById('hidden-cufe').value = data.cufe;
                    document.getElementById('provider-prefix').value = data.prefix || '';
                    document.getElementById('provider-consecutive').value = data.consecutive || '';
                    status.textContent = data.message + ' Prefijo: ' + (data.prefix || 'N/A') + ' | Consecutivo: ' + (data.consecutive || 'N/A');
                })
                .catch((error) => { status.textContent = error.message; });
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

        document.addEventListener('click', (event) => {
            if (!event.target.closest('#download-menu')) document.getElementById('download-menu').classList.remove('open');
        });

        document.querySelectorAll('.item-row').forEach(syncLineFields);
        calculateTotals();
    </script>
</body>
</html>