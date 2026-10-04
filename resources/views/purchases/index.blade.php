<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Gestión de Compras Avanzado</title>
    <!-- ESTILOS COMPACTOS LOCALES BLINDADOS PARA MONITORES HP -->
    <style>
        body { background: #f3f5f1; font-family: sans-serif; padding: 18px; font-size: 12px; color: #364640; }
        .form-container { background: white; padding: 22px; border-radius: 18px; border: 1px solid #d7dfd8; box-shadow: 0 14px 30px rgba(25,37,34,0.07); max-width: 1280px; margin: 0 auto; box-sizing: border-box; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #d7dfd8; padding-bottom: 16px; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .title-pink { color: #192522; font-weight: 900; font-size: 19px; margin: 0; letter-spacing: -0.02em; }
        .btn-group { display: flex; gap: 6px; background: #f3f5f1; padding: 6px; border-radius: 12px; border: 1px solid #d7dfd8; flex-wrap: wrap; }
        .btn-white { background: white; border: 1px solid #cbd6cf; color: #364640; padding: 7px 11px; border-radius: 8px; font-weight: 700; font-size: 11px; cursor: pointer; }
        .btn-pink-light { background: #fff0e8; border: 1px solid #f1c2ae; color: #b65338; padding: 7px 11px; border-radius: 8px; font-weight: 800; font-size: 11px; cursor: pointer; }
        .btn-pink-dark { background: #227c70; color: white; padding: 8px 15px; border-radius: 8px; font-weight: 800; font-size: 11px; border: none; cursor: pointer; }
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
        .dian-box { background: #edf7f4; padding: 14px; border-radius: 12px; border: 1px solid #b9ddd3; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; }
        .grid-header { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; background: #f8faf8; padding: 14px; border-radius: 12px; border: 1px solid #d7dfd8; margin-bottom: 18px; }
        .grid-header label { display: block; font-weight: 800; color: #227c70; text-transform: uppercase; font-size: 10px; margin-bottom: 4px; }
        .input-style { width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #cbd6cf; box-sizing: border-box; font-size: 12px; }
        .table-box { border: 1px solid #b9ddd3; border-radius: 12px; overflow-x: auto; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #192522; color: white; padding: 9px; font-size: 11px; text-transform: uppercase; text-align: left; }
        td { padding: 8px; background: white; border-bottom: 1px solid #e6ede8; }
        .payment-box { background: #f8faf8; padding: 15px; border-radius: 12px; border: 1px solid #d7dfd8; box-sizing: border-box; margin-top: 15px; }
        .totals-box { background: #fff8df; padding: 15px; border-radius: 12px; border: 1px solid #f0d98b; font-weight: 500; box-sizing: border-box; margin-top: 15px; }
        .total-row-pink { border-top: 1px solid #e7c96b; padding-top: 8px; margin-top: 8px; font-size: 14px; font-weight: 900; color: #8b6811; display: flex; justify-content: space-between; }
        .flex-box { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .account-select, .line-description, .asset-fields { display: none; }
        .asset-fields { gap: 3px; }
        .workflow-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 0 0 18px; }
        .workflow-step { display: flex; align-items: center; gap: 9px; padding: 10px 12px; border: 1px solid #d7dfd8; border-radius: 10px; background: #f8faf8; }
        .workflow-step strong { display: block; color: #192522; font-size: 11px; }
        .workflow-step small { display: block; color: #71807a; font-size: 10px; }
        .workflow-number { display: grid; width: 25px; height: 25px; place-items: center; flex: 0 0 auto; border-radius: 8px; background: #227c70; color: white; font-weight: 900; font-size: 11px; }
        .section-label { margin: 0 0 8px; color: #71807a; font-size: 10px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; }
        @media (max-width: 800px) { .grid-header { grid-template-columns: repeat(2, 1fr); } .workflow-strip { grid-template-columns: 1fr; } .top-bar > div { width: 100%; } .btn-group { justify-content: flex-start; } }
        @media (max-width: 520px) { body { padding: 8px; } .form-container { padding: 14px; border-radius: 14px; } .grid-header { grid-template-columns: 1fr; } .dian-box { align-items: flex-start; flex-direction: column; gap: 10px; } }
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
                    @php($latestPurchaseVoucherId = optional($recentPurchases->first())->accounting_voucher_id)
                    @if($latestPurchaseVoucherId)
                        <a href="{{ route('accounting.vouchers.accounting', $latestPurchaseVoucherId) }}" target="_blank" class="btn-white" style="border-color:#e9d5ff; color:#6b21a8; text-decoration:none; display:inline-block;">Ver Asiento Contable</a>
                    @else
                        <button type="button" onclick="showAccountingEntry()" class="btn-white" style="border-color:#e9d5ff; color:#6b21a8;">Ver Asiento Contable</button>
                    @endif
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

            <div class="workflow-strip" aria-label="Flujo de compra">
                <div class="workflow-step"><span class="workflow-number">01</span><span><strong>Encabezado</strong><small>Proveedor y condiciones</small></span></div>
                <div class="workflow-step"><span class="workflow-number">02</span><span><strong>Clasificación</strong><small>Producto, gasto o activo fijo</small></span></div>
                <div class="workflow-step"><span class="workflow-number">03</span><span><strong>Revisión</strong><small>Impuestos, pago y total</small></span></div>
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

            <p class="section-label">01 / Datos de la factura</p>
            <!-- ENCABEZADO FISCAL ESTILO FACTURA REAL -->
            <div class="grid-header">
                <div>
                    <label>Tipo Documento</label>
                    <select name="document_type_id" class="input-style"><option value="contado">Factura Contado (FCC)</option><option value="credito">Factura Crédito (FCR)</option></select>
                </div>
                <div><label>Consecutivo Interno</label><input type="text" name="consecutivo" id="consecutivo" value="COM-001" class="input-style" style="background:#f3f4f6; font-weight:bold;" maxlength="50"></div>
                <div><label>N° Factura Proveedor</label>@if($nextConsecutive)<input type="text" id="invoice_number" readonly value="{{ $nextConsecutive }}" title="Asignado automáticamente por la resolución DIAN activa" class="input-style"><input type="hidden" name="invoice_number" value="{{ $nextConsecutive }}">@else<input type="text" id="invoice_number" name="invoice_number" required class="input-style">@endif</div>
                <div><label>Proveedor</label><select id="provider" name="provider" required class="input-style" onchange="syncProviderNit()"><option value="">Seleccione proveedor</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->name }}" data-nit="{{ $supplier->document }}">{{ $supplier->name }}{{ $supplier->document ? ' - ' . $supplier->document : '' }}</option>@endforeach</select></div>
                <div><label>NIT / Documento</label><input type="text" id="provider-nit" name="provider_nit" class="input-style" placeholder="900123456-7" onkeydown="fillProviderFromNit(event)"></div>
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

            <p class="section-label">02 / Detalle y clasificación</p>
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

            <p class="section-label">03 / Pago y revisión final</p>
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
                        <th>NIT</th>
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
                            <td>{{ $purchase->provider_nit ?? '—' }}</td>
                            <td>{{ $purchase->creator?->name ?? 'Sistema / invitado' }}</td>
                            <td>{{ $purchase->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $purchase->deleter?->name ?? 'N/A' }}</td>
                            <td>{{ $purchase->trashed() ? 'Eliminada' : 'Activa' }}</td>
                            <td>
                                <a class="btn-white" style="text-decoration:none;display:inline-block" href="{{ route('purchases.show', $purchase) }}" target="_blank">Ver documento</a>
                                @if($purchase->accounting_voucher_id)
                                    <a class="btn-white" style="text-decoration:none;display:inline-block" target="_blank" href="{{ route('accounting.vouchers.accounting', $purchase->accounting_voucher_id) }}">Asiento contable</a>
                                @endif
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
                        <tr><td colspan="8">Aún no hay facturas registradas.</td></tr>
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

        function syncProviderNit() {
            const select = document.getElementById('provider');
            const option = select.options[select.selectedIndex];
            document.getElementById('provider-nit').value = option?.dataset.nit || '';
        }

        function fillProviderFromNit(event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();

            const nit = event.target.value.trim();
            const select = document.getElementById('provider');
            const status = document.getElementById('upload-status');
            const match = Array.from(select.options).find((option) => option.dataset.nit === nit);

            if (match) {
                select.value = match.value;
                status.textContent = 'Proveedor encontrado: ' + match.value;
            } else if (nit) {
                status.textContent = 'No encontramos un proveedor con ese NIT. Selecciónalo manualmente o regístralo en Terceros.';
            }
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
    <x-voucher-modal />
</body>
</html>