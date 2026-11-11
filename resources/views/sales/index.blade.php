<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura de venta</title>
    @vite('resources/js/app.js')
    <style>
           body{background:#f3f5f1;font-family:Arial,sans-serif;padding:18px;color:#364640;font-size:12px}.sale-shell{max-width:1280px;margin:auto;background:#fff;padding:22px;border:1px solid #d7dfd8;border-radius:18px;box-shadow:0 14px 30px rgba(25,37,34,.07)}.top-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid #d7dfd8;padding-bottom:16px;margin-bottom:18px}.title{color:#192522;font-weight:900;font-size:19px;letter-spacing:-.02em}.btn{border:1px solid #cbd6cf;background:#fff;color:#364640;padding:7px 11px;border-radius:8px;cursor:pointer;font-size:11px;font-weight:700}.btn-main{background:#227c70;color:#fff;border:0}.btn-green{background:#edf7f4;border-color:#b9ddd3;color:#227c70}.btn-group{display:flex;gap:6px;flex-wrap:wrap;background:#f3f5f1;padding:6px;border-radius:12px;border:1px solid #d7dfd8}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;background:#f8faf8;padding:14px;border:1px solid #d7dfd8;border-radius:12px}.grid label{display:block;font-weight:800;font-size:10px;color:#227c70;margin-bottom:4px}.input{width:100%;box-sizing:border-box;padding:8px;border:1px solid #cbd6cf;border-radius:8px;font-size:12px}.table-wrap{overflow-x:auto;border:1px solid #b9ddd3;border-radius:12px;margin-top:14px}table{width:100%;border-collapse:collapse}th{background:#192522;color:#fff;padding:9px;text-align:left;font-size:10px}td{padding:8px;border-bottom:1px solid #e6ede8}.totals{margin:14px 0 0 auto;max-width:300px;background:#fff8df;padding:15px;border:1px solid #f0d98b;border-radius:12px}.row{display:flex;justify-content:space-between;margin:6px 0}.total{border-top:1px solid #e7c96b;padding-top:8px;font-size:15px;font-weight:bold;color:#8b6811}.menu{position:relative}.options{display:none;position:absolute;right:0;top:100%;z-index:3;background:#fff;border:1px solid #d7dfd8;border-radius:8px;padding:4px;min-width:155px;box-shadow:0 8px 15px rgba(25,37,34,.12)}.menu.open .options{display:grid}.options button{border:0;background:#fff;text-align:left;padding:8px;cursor:pointer;font-size:11px}.options button:hover{background:#edf7f4}
        .workflow-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:0 0 18px}.workflow-step{display:flex;align-items:center;gap:9px;padding:10px 12px;border:1px solid #d7dfd8;border-radius:10px;background:#f8faf8}.workflow-step strong{display:block;color:#192522;font-size:11px}.workflow-step small{display:block;color:#71807a;font-size:10px}.workflow-number{display:grid;width:25px;height:25px;place-items:center;flex:0 0 auto;border-radius:8px;background:#227c70;color:white;font-weight:900;font-size:11px}.section-label{margin:0 0 8px;color:#71807a;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}@media(max-width:800px){.grid{grid-template-columns:repeat(2,1fr)}.workflow-strip{grid-template-columns:1fr}.top-bar>div{width:100%}.btn-group{justify-content:flex-start}}@media(max-width:520px){body{padding:8px}.sale-shell{padding:14px;border-radius:14px}.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="sale-shell">
    @if($selectedSale)
        <x-document-preview
            title="Factura de venta"
            :number="$selectedSale->invoice_number"
            :date="$selectedSale->sale_date?->format('d/m/Y')"
            party-label="Cliente"
            :party="$selectedSale->customer_name"
            :fields="[
                ['label' => 'Documento', 'value' => $selectedSale->customer_document],
                ['label' => 'Forma de pago', 'value' => $selectedSale->paymentMethod?->name],
                ['label' => 'Retención', 'value' => '$'.number_format((float) $selectedSale->retention_total, 2)],
            ]"
            :columns="[
                ['key' => 'item.code', 'label' => 'Código'],
                ['key' => 'item.name', 'label' => 'Producto'],
                ['key' => 'quantity', 'label' => 'Cantidad'],
                ['key' => 'unit_price', 'label' => 'Precio unitario'],
                ['key' => 'iva_percentage', 'label' => 'IVA %'],
            ]"
            :rows="$selectedSale->details"
            :totals="[
                ['label' => 'Subtotal', 'value' => '$'.number_format((float) $selectedSale->subtotal, 2)],
                ['label' => 'IVA', 'value' => '$'.number_format((float) $selectedSale->iva_total, 2)],
                ['label' => 'Descuentos', 'value' => '$'.number_format((float) $selectedSale->discount_total, 2)],
                ['label' => 'Total', 'value' => '$'.number_format((float) $selectedSale->total, 2)],
            ]"
            :accounting-url="$selectedSale->accounting_voucher_id ? route('accounting.vouchers.accounting', $selectedSale->accounting_voucher_id) : null"
            :accounting-action="$selectedSale->accounting_voucher_id ? null : route('sales.account', $selectedSale)"
            :accounting-message="$selectedSale->accounting_voucher_id ? null : 'La factura aún no tiene contabilización.'"
            :actions="[['label' => 'Abrir documento', 'url' => route('sales.show', $selectedSale)]]"
        />
    @endif
    <form method="POST" action="{{ route('sales.store') }}" id="sale-form">
        @csrf
        <div class="top-bar">
            <div class="flex items-center gap-3"><a href="{{ route('dashboard') }}" class="btn" style="text-decoration:none;">⌂ Tablero</a><div class="title">🧾 Factura de venta</div></div>
            <div class="btn-group">
                @if($selectedSale)
                    <a href="{{ route('sales.edit', $selectedSale) }}" class="btn" style="text-decoration:none;">Editar</a>
                @else
                    <button type="button" class="btn" onclick="showMessage('Selecciona una factura del historial para editarla.')">Editar</button>
                @endif
                <button type="button" class="btn" onclick="clearSale()">Eliminar</button>
                @if($selectedSale)
                    <a href="{{ route('commercial-documents.customer-credit-notes', ['reference_invoice_id' => $selectedSale->id]) }}" class="btn btn-green" style="text-decoration:none;">NC/NB</a>
                    <a href="{{ route('sales.statement', $selectedSale) }}" class="btn" style="text-decoration:none;" target="_blank" rel="noopener">Estado de cuenta</a>
                @else
                    <button type="button" class="btn btn-green" onclick="showMessage('Selecciona una factura del historial para crear una nota crédito.')">NC/NB</button>
                    <button type="button" class="btn" onclick="showMessage('Selecciona una factura del historial para consultar su estado de cuenta.')">Estado de cuenta</button>
                @endif
                @if($selectedSale?->accounting_voucher_id)
                    <a target="_blank" href="{{ route('accounting.vouchers.accounting', $selectedSale->accounting_voucher_id) }}" class="btn" style="text-decoration:none;">Ver contabilización</a>
                @else
                    <button type="button" class="btn" onclick="showMessage('{{ $selectedSale ? 'Esta factura aún no tiene contabilización.' : 'Selecciona una factura del historial para ver su contabilización.' }}')">Ver contabilización</button>
                @endif
                <button type="button" class="btn" style="background:#db2777;color:#fff;border-color:#db2777" onclick="sendToDian()">⚡ Enviar a la DIAN</button>
                <a href="{{ route('items.index') }}" class="btn btn-green">📦 Inventario</a>
                <div class="menu" id="more-menu">
                    <button type="button" class="btn" onclick="toggleMenu(event)">⇩ Más</button>
                    <div class="options">
                        <button type="button" onclick="copyDocument()">⧉ Copiar documento</button>
                        <button type="button" onclick="downloadCurrentXml()">⇩ Descargar XML</button>
                        <button type="button" onclick="shareByEmail()">✉ Compartir vía correo</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-main">💾 Guardar factura</button>
            </div>
        </div>

        <div class="workflow-strip" aria-label="Flujo de venta">
            <div class="workflow-step"><span class="workflow-number">01</span><span><strong>Cliente</strong><small>Datos de la factura</small></span></div>
            <div class="workflow-step"><span class="workflow-number">02</span><span><strong>Detalle</strong><small>Productos y descuentos</small></span></div>
            <div class="workflow-step"><span class="workflow-number">03</span><span><strong>Cobro</strong><small>Impuestos y total</small></span></div>
        </div>

        @if(session('success'))<div style="padding:8px;background:#ecfdf5;color:#047857;margin-bottom:10px;">{{ session('success') }}</div>@endif
        @if($errors->any())<div style="padding:8px;background:#fef2f2;color:#b91c1c;margin-bottom:10px;">{{ $errors->first() }}</div>@endif

        <p class="section-label">01 / Datos del cliente</p>
        <div class="grid">
            <div><label>N° Factura</label><input class="input" id="invoice-number" name="invoice_number" value="{{ old('skip_dian') ? ($nextConsecutive ?? 'FV-'.now()->format('YmdHis')) : old('invoice_number', $nextConsecutive ?? 'FV-'.now()->format('YmdHis')) }}" @if($nextConsecutive) readonly @endif required><small id="invoice-number-hint">El número definitivo se asigna al guardar.</small></div>
            <div><label>Fecha</label><input class="input" name="sale_date" type="date" value="{{ now()->toDateString() }}" required></div>
            <div><label>Cliente</label><select class="input" id="customer-name" name="customer_name" required><option value="">Seleccione cliente</option>@foreach($customers as $customer)<option value="{{ $customer->name }}">{{ $customer->name }}{{ $customer->document ? ' - ' . $customer->document : '' }}</option>@endforeach</select></div>
            <div><label>Documento cliente</label><input class="input" name="customer_document"></div>
            <div><label>Correo cliente</label><input class="input" id="customer-email" name="customer_email" type="email"></div>
            <div><label>Retención en ventas (practicada por el cliente)</label><select class="input" id="withholding-concept" name="withholding_concept" onchange="calculate()">@foreach($withholdings['sales_concepts'] as $conceptKey => $concept)<option value="{{ $conceptKey }}" data-rate="{{ $concept['rate'] }}" data-base-uvt="{{ $concept['base_uvt'] }}" data-base-on="{{ $concept['base_on'] }}">{{ $concept['label'] }}</option>@endforeach</select></div>
            <div><label>&nbsp;</label><label style="display:flex;align-items:center;gap:6px;font-weight:600;font-size:11px;color:#364640;"><input type="checkbox" id="skip-dian" name="skip_dian" value="1" style="width:auto;" @checked(old('skip_dian'))> No enviar a la DIAN (uso interno)</label></div>
        </div>

        <p class="section-label">02 / Productos y descuentos</p>
        <div class="table-wrap"><table><thead><tr><th style="width:30%">Producto</th><th>Presentación</th><th>Cantidad</th><th>Precio</th><th>IVA %</th><th>Descuento %</th><th>Total</th><th></th></tr></thead><tbody id="sale-lines"><tr class="sale-line"><td><select class="input product" name="items[0][item_id]" required><option value="">Seleccione producto</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code ?: 'Sin código' }} · stock {{ $item->stock }} {{ $item->base_unit ?? 'unidad' }})</option>@endforeach</select></td><td><select class="input unit-select" name="items[0][unit_conversion_id]"><option value="">Unidad base</option></select></td><td><input class="input quantity" name="items[0][quantity]" type="number" min="0.0001" step="0.0001" value="1" required></td><td><input class="input price" name="items[0][unit_price]" type="number" step="0.01" min="0" required></td><td><select class="input iva" name="items[0][iva_percentage]"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td><td><input class="input discount" name="items[0][discount_percentage]" type="number" min="0" max="100" value="0"></td><td class="line-total">$0.00</td><td><button type="button" class="btn" onclick="removeLine(this)">×</button></td></tr></tbody></table></div>
        <button type="button" class="btn btn-green" onclick="addLine()" style="margin-top:10px">＋ Agregar renglón</button>
        <p class="section-label" style="margin-top:18px;">03 / Forma de pago</p>
        <div class="grid">
            <div><label for="payment-method">Forma de pago</label><select class="input" id="payment-method" name="payment_method_id" required><option value="">Seleccione una forma de pago</option>@foreach($paymentMethods as $paymentMethod)<option value="{{ $paymentMethod->id }}" @selected(old('payment_method_id') == $paymentMethod->id)>{{ $paymentMethod->name }}</option>@endforeach</select></div>
        </div>
        <p class="section-label" style="margin-top:18px;">04 / Cobro y revisión final</p>
        <div class="totals"><div class="row"><span>Subtotal</span><strong id="subtotal">$0.00</strong></div><div class="row"><span>IVA</span><strong id="iva-total">$0.00</strong></div><div class="row"><span>Descuentos</span><strong id="discount-total">$0.00</strong></div><div class="row"><span>Retención</span><strong id="retention-total">$0.00</strong></div><div class="row total"><span>Neto a cobrar</span><strong id="grand-total">$0.00</strong></div></div>
        <input type="hidden" name="subtotal" id="subtotal-value"><input type="hidden" name="iva_total" id="iva-value"><input type="hidden" name="discount_total" id="discount-value"><input type="hidden" name="retention_base" id="retention-base-value"><input type="hidden" name="retention_total" id="retention-value"><input type="hidden" name="total" id="total-value">
    </form>

    <div class="history"><h3 class="title">Facturas recientes</h3><div class="table-wrap"><table><thead><tr><th>Factura</th><th>Cliente</th><th>Forma de pago</th><th>Total</th><th>Acciones</th></tr></thead><tbody>@forelse($sales as $sale)<tr><td>{{ $sale->invoice_number }}</td><td>{{ $sale->customer_name }}</td><td>{{ $sale->paymentMethod?->name ?? 'No especificada' }}</td><td>${{ number_format($sale->total, 2) }}</td><td><a class="btn" href="{{ route('sales.index', ['preview' => $sale->id]) }}">Vista previa</a> <a class="btn" href="{{ route('sales.show', $sale) }}" target="_blank">Ver documento</a> <a class="btn" href="{{ route('sales.edit', $sale) }}">✏ Editar</a> <a class="btn" href="{{ route('sales.xml', $sale) }}">XML</a> <a class="btn" href="{{ route('sales.statement', $sale) }}" target="_blank">Cartera</a>@if($sale->accounting_voucher_id)<a class="btn" target="_blank" href="{{ route('accounting.vouchers.accounting', $sale->accounting_voucher_id) }}">Ver contabilización</a>@else<form method="POST" action="{{ route('sales.account', $sale) }}" style="display:inline-block" onsubmit="return confirm('¿Contabilizar esta factura ahora?')">@csrf<button type="submit" class="btn">Contabilizar ahora</button></form>@endif<form method="POST" action="{{ route('sales.email', $sale) }}" style="display:inline-flex;gap:3px">@csrf<input name="email" type="email" value="{{ $sale->customer_email }}" placeholder="correo" required class="input" style="width:140px"><button class="btn btn-main">Enviar</button></form></td></tr>@empty<tr><td colspan="5">No hay facturas de venta registradas.</td></tr>@endforelse</tbody></table></div></div>
</div>
<script>
let lineIndex=1;
const products=@json($productOptions);
const invoiceNumber=document.getElementById('invoice-number'),skipDian=document.getElementById('skip-dian'),electronicConsecutive=@json($nextConsecutive),internalConsecutive=@json($nextInternalConsecutive);
let electronicNumber=invoiceNumber.value;
function syncInvoiceNumber(){if(skipDian.checked){invoiceNumber.value=internalConsecutive;invoiceNumber.readOnly=true}else{invoiceNumber.value=electronicNumber;invoiceNumber.readOnly=Boolean(electronicConsecutive)}}
skipDian.addEventListener('change',()=>{if(skipDian.checked)electronicNumber=invoiceNumber.value;syncInvoiceNumber()});syncInvoiceNumber();
function fmt(n){return (Number(n)||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}
function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,character=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]))}
function options(){return '<option value="">Seleccione producto</option>'+products.map(product=>`<option value="${product.id}">${escapeHtml(product.name)} (${escapeHtml(product.code||'Sin código')} · stock ${product.stock} ${escapeHtml(product.base_unit)})</option>`).join('')}
function syncUnitOptions(row,resetPrice=false){const product=products.find(entry=>String(entry.id)===row.querySelector('.product').value),select=row.querySelector('.unit-select'),previous=select.value||select.dataset.selected||'';select.innerHTML=`<option value="">${escapeHtml(product?.base_unit||'unidad')} (base)</option>`+(product?.units||[]).map(unit=>`<option value="${unit.id}" data-factor="${unit.factor}">${escapeHtml(unit.name)} × ${unit.factor}</option>`).join('');if(!resetPrice&&[...select.options].some(option=>option.value===previous))select.value=previous;delete select.dataset.selected;if(resetPrice)updatePrice(row)}
function updatePrice(row){const product=products.find(entry=>String(entry.id)===row.querySelector('.product').value),unit=product?.units.find(entry=>String(entry.id)===row.querySelector('.unit-select').value),unitPrice=unit&&unit.sale_price!==null&&unit.sale_price!==undefined?Number(unit.sale_price):Number(product?.price)*(Number(unit?.factor)||1);if(product)row.querySelector('.price').value=unitPrice.toFixed(2)}
function addLine(){const row=document.createElement('tr');row.className='sale-line';row.innerHTML=`<td><select class="input product" name="items[${lineIndex}][item_id]" required>${options()}</select></td><td><select class="input unit-select" name="items[${lineIndex}][unit_conversion_id]"><option value="">Unidad base</option></select></td><td><input class="input quantity" name="items[${lineIndex}][quantity]" type="number" min="0.0001" step="0.0001" value="1" required></td><td><input class="input price" name="items[${lineIndex}][unit_price]" type="number" step="0.01" min="0" required></td><td><select class="input iva" name="items[${lineIndex}][iva_percentage]"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td><td><input class="input discount" name="items[${lineIndex}][discount_percentage]" type="number" min="0" max="100" value="0"></td><td class="line-total">$0.00</td><td><button type="button" class="btn" onclick="removeLine(this)">×</button></td>`;document.getElementById('sale-lines').appendChild(row);lineIndex++}
function removeLine(button){if(document.querySelectorAll('.sale-line').length>1)button.closest('tr').remove();calculate()}
function calculate(){let subtotal=0,iva=0,discount=0;document.querySelectorAll('.sale-line').forEach(row=>{const quantity=Number(row.querySelector('.quantity').value||0),price=Number(row.querySelector('.price').value||0),rate=Number(row.querySelector('.iva').value||0),discountRate=Number(row.querySelector('.discount').value||0),lineSubtotal=quantity*price,lineDiscount=lineSubtotal*discountRate/100,lineIva=(lineSubtotal-lineDiscount)*rate/100;subtotal+=lineSubtotal;discount+=lineDiscount;iva+=lineIva;row.querySelector('.line-total').textContent='$'+fmt(lineSubtotal-lineDiscount+lineIva)});const selected=document.getElementById('withholding-concept').selectedOptions[0],retentionRate=Number(selected?.dataset.rate||0),baseUvt=Number(selected?.dataset.baseUvt||0),baseOn=selected?.dataset.baseOn||'subtotal',retentionBaseValue=baseOn==='iva'?iva:subtotal-discount,minimumBase=baseUvt*{{ $withholdings['uvt'] }},taxableBase=retentionBaseValue>=minimumBase?retentionBaseValue:0,retention=taxableBase*retentionRate,total=subtotal-discount+iva-retention;document.getElementById('subtotal').textContent='$'+fmt(subtotal);document.getElementById('iva-total').textContent='$'+fmt(iva);document.getElementById('discount-total').textContent='$'+fmt(discount);document.getElementById('retention-total').textContent='$'+fmt(retention);document.getElementById('grand-total').textContent='$'+fmt(total);document.getElementById('subtotal-value').value=subtotal.toFixed(2);document.getElementById('iva-value').value=iva.toFixed(2);document.getElementById('discount-value').value=discount.toFixed(2);document.getElementById('retention-base-value').value=taxableBase.toFixed(2);document.getElementById('retention-value').value=retention.toFixed(2);document.getElementById('total-value').value=total.toFixed(2)}
function editSale(){document.querySelectorAll('#sale-form input,#sale-form select').forEach(field=>field.disabled=false);document.getElementById('customer-name').focus()}
function clearSale(){if(confirm('¿Limpiar la factura?')){document.getElementById('sale-form').reset();electronicNumber=invoiceNumber.defaultValue;syncInvoiceNumber();document.querySelectorAll('.sale-line:not(:first-child)').forEach(row=>row.remove());calculate()}}
function showMessage(message){alert(message)}
function toggleMenu(event){event.stopPropagation();document.getElementById('more-menu').classList.toggle('open')}
function copyDocument(){navigator.clipboard?.writeText(document.getElementById('sale-form').innerText||location.href);showMessage('Documento copiado')}
function downloadCurrentXml(){showMessage('Guarda la factura para descargar su XML desde el historial')}
function shareByEmail(){document.getElementById('customer-email').focus();showMessage('Escribe el correo del cliente y guarda la factura para compartirla')}
function sendToDian(){showMessage('La factura quedará lista para envío a la DIAN cuando se configuren las credenciales y el certificado digital.')}
document.addEventListener('change',event=>{const row=event.target.closest('.sale-line');if(row&&event.target.matches('.product'))syncUnitOptions(row,true);else if(row&&event.target.matches('.unit-select'))updatePrice(row);calculate()});document.addEventListener('input',calculate);document.addEventListener('click',event=>{if(!event.target.closest('#more-menu'))document.getElementById('more-menu').classList.remove('open')});document.querySelectorAll('.sale-line').forEach(row=>syncUnitOptions(row));calculate()
</script>
<x-voucher-modal />
</body>
</html>
