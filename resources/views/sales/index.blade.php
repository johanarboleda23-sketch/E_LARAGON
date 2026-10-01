<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura de venta</title>
    <style>
        body{background:#f0fdf4;font-family:Arial,sans-serif;padding:12px;color:#374151;font-size:12px}.sale-shell{max-width:1200px;margin:auto;background:#fff;padding:18px;border:1px solid #bbf7d0;border-radius:12px;box-shadow:0 8px 20px #064e3b12}.top-bar{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;border-bottom:2px solid #bbf7d0;padding-bottom:10px;margin-bottom:14px}.title{color:#047857;font-weight:bold;font-size:17px}.btn{border:1px solid #d1d5db;background:#fff;padding:6px 9px;border-radius:6px;cursor:pointer;font-size:11px;font-weight:bold}.btn-main{background:#059669;color:#fff;border:0}.btn-green{background:#d1fae5;border-color:#86efac;color:#047857}.btn-group{display:flex;gap:4px;flex-wrap:wrap}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;background:#ecfdf5;padding:10px;border:1px solid #bbf7d0;border-radius:8px}.grid label{display:block;font-weight:bold;font-size:10px;color:#047857;margin-bottom:3px}.input{width:100%;box-sizing:border-box;padding:7px;border:1px solid #a7f3d0;border-radius:5px;font-size:12px}.table-wrap{overflow-x:auto;border:1px solid #10b981;border-radius:8px;margin-top:14px}table{width:100%;border-collapse:collapse}th{background:#059669;color:#fff;padding:8px;text-align:left;font-size:10px}td{padding:7px;border-bottom:1px solid #d1fae5}.totals{margin:14px 0 0 auto;max-width:300px;background:#ecfdf5;padding:12px;border:1px solid #bbf7d0;border-radius:8px}.row{display:flex;justify-content:space-between;margin:5px 0}.total{border-top:1px solid #86efac;padding-top:8px;font-size:15px;font-weight:bold;color:#047857}.menu{position:relative}.options{display:none;position:absolute;right:0;top:100%;z-index:3;background:#fff;border:1px solid #bbf7d0;border-radius:6px;padding:4px;min-width:155px;box-shadow:0 8px 15px #064e3b22}.menu.open .options{display:grid}.options button{border:0;background:#fff;text-align:left;padding:7px;cursor:pointer;font-size:11px}.options button:hover{background:#ecfdf5}.history{margin-top:18px}.history table{font-size:11px}@media(max-width:700px){.grid{grid-template-columns:repeat(2,1fr)}}
    </style>
</head>
<body>
<div class="sale-shell">
    <form method="POST" action="{{ route('sales.store') }}" id="sale-form">
        @csrf
        <div class="top-bar">
            <div class="flex items-center gap-3"><a href="{{ route('dashboard') }}" class="btn" style="text-decoration:none;">⌂ Tablero</a><div class="title">🧾 Factura de venta</div></div>
            <div class="btn-group">
                <button type="button" class="btn" onclick="editSale()">Editar</button>
                <button type="button" class="btn" onclick="clearSale()">Eliminar</button>
                <button type="button" class="btn btn-green" onclick="showMessage('Notas crédito y débito se gestionan desde la factura guardada.')">NC/NB</button>
                <button type="button" class="btn" onclick="document.getElementById('customer-email').focus()">Estado de cuenta</button>
                <button type="button" class="btn" onclick="showMessage('La contabilización se generará con el comprobante de venta.')">Ver contabilización</button>
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

        @if(session('success'))<div style="padding:8px;background:#ecfdf5;color:#047857;margin-bottom:10px;">{{ session('success') }}</div>@endif
        @if($errors->any())<div style="padding:8px;background:#fef2f2;color:#b91c1c;margin-bottom:10px;">{{ $errors->first() }}</div>@endif

        <div class="grid">
            <div><label>N° Factura</label><input class="input" id="invoice-number" name="invoice_number" value="FV-{{ now()->format('YmdHis') }}" required></div>
            <div><label>Fecha</label><input class="input" name="sale_date" type="date" value="{{ now()->toDateString() }}" required></div>
            <div><label>Cliente</label><select class="input" id="customer-name" name="customer_name" required><option value="">Seleccione cliente</option>@foreach($customers as $customer)<option value="{{ $customer->name }}">{{ $customer->name }}{{ $customer->document ? ' - ' . $customer->document : '' }}</option>@endforeach</select></div>
            <div><label>Documento cliente</label><input class="input" name="customer_document"></div>
            <div><label>Correo cliente</label><input class="input" id="customer-email" name="customer_email" type="email"></div>
            <div><label>Retención</label><select class="input" id="withholding-concept" name="withholding_concept" onchange="calculate()"><option value="none">No aplicar retención</option>@foreach($withholdings['concepts'] as $conceptKey => $concept)<option value="{{ $conceptKey }}" data-rate="{{ $concept['rate'] }}" data-base-uvt="{{ $concept['base_uvt'] }}" data-base-on="{{ $concept['base_on'] }}">{{ $concept['label'] }}</option>@endforeach</select></div>
        </div>

        <div class="table-wrap"><table><thead><tr><th style="width:35%">Producto</th><th>Cantidad</th><th>Precio</th><th>IVA %</th><th>Descuento %</th><th>Total</th><th></th></tr></thead><tbody id="sale-lines"><tr class="sale-line"><td><select class="input product" name="items[0][item_id]" required><option value="">Seleccione producto</option>@foreach($items as $item)<option value="{{ $item->id }}" data-price="{{ $item->sale_price }}" data-stock="{{ $item->stock }}">{{ $item->name }} (stock {{ $item->stock }})</option>@endforeach</select></td><td><input class="input quantity" name="items[0][quantity]" type="number" min="1" value="1" required></td><td><input class="input price" name="items[0][unit_price]" type="number" step="0.01" min="0" required></td><td><select class="input iva" name="items[0][iva_percentage]"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td><td><input class="input discount" name="items[0][discount_percentage]" type="number" min="0" max="100" value="0"></td><td class="line-total">$0.00</td><td><button type="button" class="btn" onclick="removeLine(this)">×</button></td></tr></tbody></table></div>
        <button type="button" class="btn btn-green" onclick="addLine()" style="margin-top:10px">＋ Agregar renglón</button>
        <div class="totals"><div class="row"><span>Subtotal</span><strong id="subtotal">$0.00</strong></div><div class="row"><span>IVA</span><strong id="iva-total">$0.00</strong></div><div class="row"><span>Descuentos</span><strong id="discount-total">$0.00</strong></div><div class="row"><span>Retención</span><strong id="retention-total">$0.00</strong></div><div class="row total"><span>Neto a cobrar</span><strong id="grand-total">$0.00</strong></div></div>
        <input type="hidden" name="subtotal" id="subtotal-value"><input type="hidden" name="iva_total" id="iva-value"><input type="hidden" name="discount_total" id="discount-value"><input type="hidden" name="retention_base" id="retention-base-value"><input type="hidden" name="retention_total" id="retention-value"><input type="hidden" name="total" id="total-value">
    </form>

    <div class="history"><h3 class="title">Facturas recientes</h3><div class="table-wrap"><table><thead><tr><th>Factura</th><th>Cliente</th><th>Total</th><th>Acciones</th></tr></thead><tbody>@forelse($sales as $sale)<tr><td>{{ $sale->invoice_number }}</td><td>{{ $sale->customer_name }}</td><td>${{ number_format($sale->total, 2) }}</td><td><a class="btn" href="{{ route('sales.xml', $sale) }}">XML</a><form method="POST" action="{{ route('sales.email', $sale) }}" style="display:inline-flex;gap:3px">@csrf<input name="email" type="email" value="{{ $sale->customer_email }}" placeholder="correo" required class="input" style="width:140px"><button class="btn btn-main">Enviar</button></form></td></tr>@empty<tr><td colspan="4">No hay facturas de venta registradas.</td></tr>@endforelse</tbody></table></div></div>
</div>
<script>
let lineIndex=1;
const products=@json($productOptions);
function options(){return '<option value="">Seleccione producto</option>'+products.map(product=>`<option value="${product.id}" data-price="${product.price}" data-stock="${product.stock}">${product.name} (stock ${product.stock})</option>`).join('')}
function addLine(){const row=document.createElement('tr');row.className='sale-line';row.innerHTML=`<td><select class="input product" name="items[${lineIndex}][item_id]" required>${options()}</select></td><td><input class="input quantity" name="items[${lineIndex}][quantity]" type="number" min="1" value="1" required></td><td><input class="input price" name="items[${lineIndex}][unit_price]" type="number" step="0.01" min="0" required></td><td><select class="input iva" name="items[${lineIndex}][iva_percentage]"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td><td><input class="input discount" name="items[${lineIndex}][discount_percentage]" type="number" min="0" max="100" value="0"></td><td class="line-total">$0.00</td><td><button type="button" class="btn" onclick="removeLine(this)">×</button></td>`;document.getElementById('sale-lines').appendChild(row);lineIndex++}
function removeLine(button){if(document.querySelectorAll('.sale-line').length>1)button.closest('tr').remove();calculate()}
function calculate(){let subtotal=0,iva=0,discount=0;document.querySelectorAll('.sale-line').forEach(row=>{const quantity=Number(row.querySelector('.quantity').value||0),price=Number(row.querySelector('.price').value||0),rate=Number(row.querySelector('.iva').value||0),discountRate=Number(row.querySelector('.discount').value||0),lineSubtotal=quantity*price,lineDiscount=lineSubtotal*discountRate/100,lineIva=(lineSubtotal-lineDiscount)*rate/100;subtotal+=lineSubtotal;discount+=lineDiscount;iva+=lineIva;row.querySelector('.line-total').textContent='$'+(lineSubtotal-lineDiscount+lineIva).toFixed(2)});const selected=document.getElementById('withholding-concept').selectedOptions[0],retentionRate=Number(selected?.dataset.rate||0),baseUvt=Number(selected?.dataset.baseUvt||0),baseOn=selected?.dataset.baseOn||'subtotal',retentionBaseValue=baseOn==='iva'?iva:subtotal-discount,minimumBase=baseUvt*{{ $withholdings['uvt'] }},taxableBase=retentionBaseValue>=minimumBase?retentionBaseValue:0,retention=taxableBase*retentionRate,total=subtotal-discount+iva-retention;document.getElementById('subtotal').textContent='$'+subtotal.toFixed(2);document.getElementById('iva-total').textContent='$'+iva.toFixed(2);document.getElementById('discount-total').textContent='$'+discount.toFixed(2);document.getElementById('retention-total').textContent='$'+retention.toFixed(2);document.getElementById('grand-total').textContent='$'+total.toFixed(2);document.getElementById('subtotal-value').value=subtotal.toFixed(2);document.getElementById('iva-value').value=iva.toFixed(2);document.getElementById('discount-value').value=discount.toFixed(2);document.getElementById('retention-base-value').value=taxableBase.toFixed(2);document.getElementById('retention-value').value=retention.toFixed(2);document.getElementById('total-value').value=total.toFixed(2)}
function editSale(){document.querySelectorAll('#sale-form input,#sale-form select').forEach(field=>field.disabled=false);document.getElementById('customer-name').focus()}
function clearSale(){if(confirm('¿Limpiar la factura?')){document.getElementById('sale-form').reset();document.querySelectorAll('.sale-line:not(:first-child)').forEach(row=>row.remove());calculate()}}
function showMessage(message){alert(message)}
function toggleMenu(event){event.stopPropagation();document.getElementById('more-menu').classList.toggle('open')}
function copyDocument(){navigator.clipboard?.writeText(document.getElementById('sale-form').innerText||location.href);showMessage('Documento copiado')}
function downloadCurrentXml(){showMessage('Guarda la factura para descargar su XML desde el historial')}
function shareByEmail(){document.getElementById('customer-email').focus();showMessage('Escribe el correo del cliente y guarda la factura para compartirla')}
function sendToDian(){showMessage('La factura quedará lista para envío a la DIAN cuando se configuren las credenciales y el certificado digital.')}
document.addEventListener('change',event=>{if(event.target.matches('.product')){const option=event.target.selectedOptions[0];event.target.closest('.sale-line').querySelector('.price').value=option?.dataset.price||0}calculate()});document.addEventListener('input',calculate);document.addEventListener('click',event=>{if(!event.target.closest('#more-menu'))document.getElementById('more-menu').classList.remove('open')});calculate()
</script>
</body>
</html>
