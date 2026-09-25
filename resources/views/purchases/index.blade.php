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
    </style>
</head>
<body>

    <div class="form-container">
        <!-- FORMULARIO GENERAL DE COMPRAS -->
        <form action="{{ route('purchases.store') }}" method="POST" id="purchase-form">
            @csrf

            <!-- BARRA SUPERIOR PROFESIONAL -->
            <div class="top-bar">
                <div><h2 class="title-pink">🛒 Módulo de Gestión de Compras Avanzado</h2></div>
                <div class="btn-group">
                    <button type="button" onclick="handleTopButton('Editar')" class="btn-white">Editar</button>
                    <button type="button" onclick="handleTopButton('Eliminar')" class="btn-white">Eliminar</button>
                    <button type="button" onclick="handleTopButton('Notas')" class="btn-pink-light">Notas Crédito / Débito</button>
                    <button type="button" onclick="handleTopButton('Cartera')" class="btn-white" style="border-color:#bfdbfe; color:#1d4ed8;">Estado Cartera (C x P)</button>
                    <button type="button" onclick="handleTopButton('PUC')" class="btn-white" style="border-color:#e9d5ff; color:#6b21a8;">Ver Asiento Contable</button>
                    <a href="/items" class="btn-pink-light" style="text-decoration:none; display:inline-block; line-height:14px;">📦 Almacén / Inventario</a>
                    <button type="submit" class="btn-pink-dark">💾 Guardar Factura</button>
                </div>
            </div>

            <!-- CARGADOR ROBÓTICO XML DIAN -->
            <div class="dian-box">
                <div>
                    <h4 style="color:#9d174d; font-weight:bold; margin:0 0 2px 0; text-transform: uppercase; font-size:11px;">⚡ Causación Automática IA + DIAN</h4>
                    <p style="color:#6b7280; font-size:10px; margin:0;">Sube el archivo XML de tu proveedor para rellenar NIT, Factura y Totales mediante el código CUFE.</p>
                </div>
                <div>
                    <input type="file" id="xml_file" accept=".xml" onchange="processDIANXml()" style="display:none;">
                    <button type="button" onclick="document.getElementById('xml_file').click()" class="btn-pink-dark" style="background:#059669; padding:4px 10px;">⚡ Cargar XML DIAN</button>
                    <span id="upload-status" style="margin-left:10px; font-style:italic; font-weight:bold;"></span>
                </div>
            </div>

            <input type="hidden" name="cufe_dian" id="hidden-cufe">

            <!-- ENCABEZADO FISCAL ESTILO FACTURA REAL -->
            <div class="grid-header">
                <div>
                    <label>Tipo Documento</label>
                    <select name="document_type_id" class="input-style"><option value="contado">Factura Contado (FCC)</option><option value="credito">Factura Crédito (FCR)</option></select>
                </div>
                <div><label>Consecutivo Interno</label><input type="text" name="consecutivo" value="COM-001" class="input-style" style="background:#f3f4f6; font-weight:bold;" readonly></div>
                <div><label>N° Factura Proveedor</label><input type="text" id="invoice_number" name="invoice_number" required class="input-style"></div>
                <div><label>Proveedor / NIT</label><input type="text" id="provider" name="provider" required class="input-style"></div>
                <div>
                    <label>Responsabilidad Fiscal</label>
                    <select name="provider_regimen" id="provider-regimen" onchange="calculateTotals()" required class="input-style"><option value="comun">Régimen Común</option><option value="simplificado">Régimen Simplificado</option><option value="gran_contribuyente">Gran Contribuyente</option></select>
                </div>
            </div>

            <!-- ENLACE MAESTRO CON TU ALMACÉN -->
            <datalist id="products-list">
                @foreach(app('App\Models\Item')->all() as $p)<option value="{{ $p->name }}" data-id="{{ $p->id }}"></option>@endforeach
            </datalist>

            <!-- TABLA MULTI-RENGLÓN COMPACTA -->
            <div class="table-box">
                <table id="items-table">
                    <thead>
                        <tr>
                            <th style="width:38%;">Producto o Referencia ( predictor Almacén )</th>
                            <th style="text-align:center; width:10%;">Cant.</th>
                            <th style="text-align:right; width:15%;">Costo ($)</th>
                            <th style="text-align:center; width:12%;">IVA</th>
                            <th style="text-align:center; width:12%;">Ganancia (%)</th>
                            <th style="text-align:right; width:10%;">Total ($)</th>
                            <th style="width:3%;"></th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr class="item-row">
                            <td><input type="text" list="products-list" name="items[0][product_name]" placeholder="🔍 Escriba artículo..." required class="input-style product-search"><input type="hidden" name="items[0][item_id]" class="product-id"></td>
                            <td><input type="number" name="items[0][quantity]" class="qty-input input-style" style="text-align:center;" value="1" min="1" required></td>
                            <td><input type="number" step="0.01" name="items[0][cost_price]" class="price-input input-style" style="text-align:right;" placeholder="0.00" required></td>
                            <td><select name="items[0][iva_percentage]" class="iva-input input-style" style="text-align:center;"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td>
                                <td><input type="number" name="items[0][utility_percentage]" class="utility-input input-style" style="text-align:center;" min="0" step="0.01" value="0"></td>
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
                    <div class="total-row-pink"><span>Total</span><strong id="grand-total">$0.00</strong></div>
                </div>
            </div>

            <input type="hidden" name="subtotal" id="subtotal-value" value="0">
            <input type="hidden" name="iva_total" id="iva-value" value="0">
            <input type="hidden" name="total_pagar" id="total-value" value="0">
        </form>
    </div>

    <script>
        let rowIndex = 1;

        function addRow() {
            const tableBody = document.getElementById('table-body');
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.innerHTML = `
                <td><input type="text" list="products-list" name="items[${rowIndex}][product_name]" placeholder="Escriba artículo..." required class="input-style product-search"><input type="hidden" name="items[${rowIndex}][item_id]" class="product-id"></td>
                <td><input type="number" name="items[${rowIndex}][quantity]" class="qty-input input-style" style="text-align:center;" value="1" min="1" required></td>
                <td><input type="number" step="0.01" name="items[${rowIndex}][cost_price]" class="price-input input-style" style="text-align:right;" placeholder="0.00" required></td>
                <td><select name="items[${rowIndex}][iva_percentage]" class="iva-input input-style" style="text-align:center;"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td>
                <td><input type="number" name="items[${rowIndex}][utility_percentage]" class="utility-input input-style" style="text-align:center;" min="0" step="0.01" value="0"></td>
                <td class="row-total" style="text-align:right; font-weight:bold;">$0.00</td>
                <td style="text-align:center;"><button type="button" onclick="removeRow(this)" style="color:#f87171; background:none; border:none; font-size:16px; font-weight:bold; cursor:pointer;">&times;</button></td>
            `;
            tableBody.appendChild(row);
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

            const total = subtotal + ivaTotal;
            document.getElementById('subtotal-total').textContent = formatCurrency(subtotal);
            document.getElementById('iva-total').textContent = formatCurrency(ivaTotal);
            document.getElementById('grand-total').textContent = formatCurrency(total);
            document.getElementById('subtotal-value').value = subtotal.toFixed(2);
            document.getElementById('iva-value').value = ivaTotal.toFixed(2);
            document.getElementById('total-value').value = total.toFixed(2);
        }

        function formatCurrency(value) {
            return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function handleTopButton(action) {
            window.alert('La opción "' + action + '" estará disponible desde el listado de compras.');
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
                    status.textContent = data.message;
                })
                .catch((error) => { status.textContent = error.message; });
        }

        document.addEventListener('input', (event) => {
            if (event.target.closest('.item-row')) calculateTotals();
        });

        document.addEventListener('change', (event) => {
            if (event.target.matches('.product-search')) {
                const option = Array.from(document.querySelectorAll('#products-list option')).find((item) => item.value === event.target.value);
                event.target.closest('.item-row').querySelector('.product-id').value = option?.dataset.id || '';
            }
            if (event.target.closest('.item-row')) calculateTotals();
        });

        calculateTotals();
    </script>
</body>
</html>