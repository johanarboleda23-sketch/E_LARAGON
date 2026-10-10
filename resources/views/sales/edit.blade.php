<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar factura {{ $sale->invoice_number }}</title>
    <style>
        body{background:#f3f5f1;font-family:Arial,sans-serif;padding:18px;color:#364640;font-size:12px}
        .sale-shell{max-width:1280px;margin:auto;background:#fff;padding:22px;border:1px solid #d7dfd8;border-radius:18px;box-shadow:0 14px 30px rgba(25,37,34,.07)}
        .top-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid #d7dfd8;padding-bottom:16px;margin-bottom:18px}
        .title{color:#192522;font-weight:900;font-size:19px;letter-spacing:-.02em}
        .btn{border:1px solid #cbd6cf;background:#fff;color:#364640;padding:7px 11px;border-radius:8px;cursor:pointer;font-size:11px;font-weight:700;text-decoration:none;display:inline-block}
        .btn-main{background:#227c70;color:#fff;border:0}
        .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;background:#f8faf8;padding:14px;border:1px solid #d7dfd8;border-radius:12px}
        .grid label{display:block;font-weight:800;font-size:10px;color:#227c70;margin-bottom:4px}
        .input{width:100%;box-sizing:border-box;padding:8px;border:1px solid #cbd6cf;border-radius:8px;font-size:12px}
        .table-wrap{overflow-x:auto;border:1px solid #b9ddd3;border-radius:12px;margin-top:14px}
        table{width:100%;border-collapse:collapse}
        th{background:#192522;color:#fff;padding:9px;text-align:left;font-size:10px}
        td{padding:8px;border-bottom:1px solid #e6ede8}
        .totals{margin:14px 0 0 auto;max-width:300px;background:#fff8df;padding:15px;border:1px solid #f0d98b;border-radius:12px}
        .row{display:flex;justify-content:space-between;margin:6px 0}
        .total{border-top:1px solid #e7c96b;padding-top:8px;font-size:15px;font-weight:bold;color:#8b6811}
        .section-label{margin:14px 0 8px;color:#71807a;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
        .alert{padding:8px;background:#ecfdf5;color:#047857;margin-bottom:10px;border-radius:6px}
        .alert-warn{padding:10px;background:#fff8df;color:#8b6811;margin-bottom:12px;border-radius:8px;font-weight:bold}
        @media(max-width:800px){.grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:520px){body{padding:8px}.sale-shell{padding:14px;border-radius:14px}.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="sale-shell">
    @if(session('success'))<div class="alert">{{ session('success') }}</div>@endif
    <div class="alert-warn">⚠ Al guardar, se revierte el stock y la contabilización anteriores de esta factura y se vuelven a calcular con los datos nuevos.</div>

    <form method="POST" action="{{ route('sales.update', $sale) }}" id="sale-form">
        @csrf
        @method('PUT')
        <div class="top-bar">
            <div class="flex items-center gap-3"><a href="{{ route('sales.show', $sale) }}" class="btn">⌂ Volver a la factura</a><div class="title">✏ Editar factura {{ $sale->invoice_number }}</div></div>
            <button type="submit" class="btn btn-main">💾 Guardar cambios</button>
        </div>

        @if($errors->any())<div style="padding:8px;background:#fef2f2;color:#b91c1c;margin-bottom:10px;">{{ $errors->first() }}</div>@endif

        <p class="section-label">01 / Datos del cliente</p>
        <div class="grid">
            <div><label>N° Factura</label><input class="input" name="invoice_number" value="{{ old('invoice_number', $sale->invoice_number) }}" required></div>
            <div><label>Fecha</label><input class="input" name="sale_date" type="date" value="{{ old('sale_date', $sale->sale_date->toDateString()) }}" required></div>
            <div><label>Cliente</label><select class="input" id="customer-name" name="customer_name" required><option value="">Seleccione cliente</option>@foreach($customers as $customer)<option value="{{ $customer->name }}" @selected(old('customer_name', $sale->customer_name) === $customer->name)>{{ $customer->name }}{{ $customer->document ? ' - ' . $customer->document : '' }}</option>@endforeach</select></div>
            <div><label>Documento cliente</label><input class="input" name="customer_document" value="{{ old('customer_document', $sale->customer_document) }}"></div>
            <div><label>Correo cliente</label><input class="input" name="customer_email" type="email" value="{{ old('customer_email', $sale->customer_email) }}"></div>
            <div><label>Retención</label><select class="input" id="withholding-concept" name="withholding_concept" onchange="calculate()"><option value="none" @selected($sale->withholding_concept === 'none')>No aplicar retención</option>@foreach($withholdings['concepts'] as $conceptKey => $concept)<option value="{{ $conceptKey }}" data-rate="{{ $concept['rate'] }}" data-base-uvt="{{ $concept['base_uvt'] }}" data-base-on="{{ $concept['base_on'] }}" @selected($sale->withholding_concept === $conceptKey)>{{ $concept['label'] }}</option>@endforeach</select></div>
            <div><label>&nbsp;</label><label style="display:flex;align-items:center;gap:6px;font-weight:600;font-size:11px;color:#364640;"><input type="checkbox" name="skip_dian" value="1" style="width:auto;" @checked($sale->skip_dian)> No enviar a la DIAN (uso interno)</label></div>
        </div>

        <p class="section-label">02 / Productos y descuentos</p>
        <div class="table-wrap"><table><thead><tr><th style="width:35%">Producto</th><th>Cantidad</th><th>Precio</th><th>IVA %</th><th>Descuento %</th><th>Total</th><th></th></tr></thead>
            <tbody id="sale-lines">
                @foreach($sale->details as $index => $detail)
                <tr class="sale-line">
                    <td><select class="input product" name="items[{{ $index }}][item_id]" required>
                        <option value="">Seleccione producto</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" data-price="{{ $item->sale_price }}" data-stock="{{ $item->stock }}" @selected($detail->item_id === $item->id)>{{ $item->name }} (stock {{ $item->stock }})</option>
                        @endforeach
                    </select></td>
                    <td><input class="input quantity" name="items[{{ $index }}][quantity]" type="number" min="1" value="{{ $detail->quantity }}" required></td>
                    <td><input class="input price" name="items[{{ $index }}][unit_price]" type="number" step="0.01" min="0" value="{{ $detail->unit_price }}" required></td>
                    <td><select class="input iva" name="items[{{ $index }}][iva_percentage]"><option value="19" @selected((float)$detail->iva_percentage===19.0)>19%</option><option value="5" @selected((float)$detail->iva_percentage===5.0)>5%</option><option value="0" @selected((float)$detail->iva_percentage===0.0)>0%</option></select></td>
                    <td><input class="input discount" name="items[{{ $index }}][discount_percentage]" type="number" min="0" max="100" value="{{ $detail->discount_percentage }}"></td>
                    <td class="line-total">$0.00</td>
                    <td><button type="button" class="btn" onclick="removeLine(this)">×</button></td>
                </tr>
                @endforeach
            </tbody>
        </table></div>
        <button type="button" class="btn" style="background:#edf7f4;border-color:#b9ddd3;color:#227c70;margin-top:10px" onclick="addLine()">＋ Agregar renglón</button>

        <p class="section-label">03 / Cobro y revisión final</p>
        <div class="totals">
            <div class="row"><span>Subtotal</span><strong id="subtotal">$0.00</strong></div>
            <div class="row"><span>IVA</span><strong id="iva-total">$0.00</strong></div>
            <div class="row"><span>Descuentos</span><strong id="discount-total">$0.00</strong></div>
            <div class="row"><span>Retención</span><strong id="retention-total">$0.00</strong></div>
            <div class="row total"><span>Neto a cobrar</span><strong id="grand-total">$0.00</strong></div>
        </div>
        <input type="hidden" name="subtotal" id="subtotal-value">
        <input type="hidden" name="iva_total" id="iva-value">
        <input type="hidden" name="discount_total" id="discount-value">
        <input type="hidden" name="retention_base" id="retention-base-value">
        <input type="hidden" name="retention_total" id="retention-value">
        <input type="hidden" name="total" id="total-value">
    </form>
</div>
<script>
let lineIndex={{ $sale->details->count() }};
const products=@json($productOptions);
function options(){return '<option value="">Seleccione producto</option>'+products.map(product=>`<option value="${product.id}" data-price="${product.price}" data-stock="${product.stock}">${product.name} (stock ${product.stock})</option>`).join('')}
function addLine(){const row=document.createElement('tr');row.className='sale-line';row.innerHTML=`<td><select class="input product" name="items[${lineIndex}][item_id]" required>${options()}</select></td><td><input class="input quantity" name="items[${lineIndex}][quantity]" type="number" min="1" value="1" required></td><td><input class="input price" name="items[${lineIndex}][unit_price]" type="number" step="0.01" min="0" required></td><td><select class="input iva" name="items[${lineIndex}][iva_percentage]"><option value="19">19%</option><option value="5">5%</option><option value="0">0%</option></select></td><td><input class="input discount" name="items[${lineIndex}][discount_percentage]" type="number" min="0" max="100" value="0"></td><td class="line-total">$0.00</td><td><button type="button" class="btn" onclick="removeLine(this)">×</button></td>`;document.getElementById('sale-lines').appendChild(row);lineIndex++}
function removeLine(button){if(document.querySelectorAll('.sale-line').length>1)button.closest('tr').remove();calculate()}
function calculate(){let subtotal=0,iva=0,discount=0;document.querySelectorAll('.sale-line').forEach(row=>{const quantity=Number(row.querySelector('.quantity').value||0),price=Number(row.querySelector('.price').value||0),rate=Number(row.querySelector('.iva').value||0),discountRate=Number(row.querySelector('.discount').value||0),lineSubtotal=quantity*price,lineDiscount=lineSubtotal*discountRate/100,lineIva=(lineSubtotal-lineDiscount)*rate/100;subtotal+=lineSubtotal;discount+=lineDiscount;iva+=lineIva;row.querySelector('.line-total').textContent='$'+(lineSubtotal-lineDiscount+lineIva).toFixed(2)});const selected=document.getElementById('withholding-concept').selectedOptions[0],retentionRate=Number(selected?.dataset.rate||0),baseUvt=Number(selected?.dataset.baseUvt||0),baseOn=selected?.dataset.baseOn||'subtotal',retentionBaseValue=baseOn==='iva'?iva:subtotal-discount,minimumBase=baseUvt*{{ $withholdings['uvt'] }},taxableBase=retentionBaseValue>=minimumBase?retentionBaseValue:0,retention=taxableBase*retentionRate,total=subtotal-discount+iva-retention;document.getElementById('subtotal').textContent='$'+subtotal.toFixed(2);document.getElementById('iva-total').textContent='$'+iva.toFixed(2);document.getElementById('discount-total').textContent='$'+discount.toFixed(2);document.getElementById('retention-total').textContent='$'+retention.toFixed(2);document.getElementById('grand-total').textContent='$'+total.toFixed(2);document.getElementById('subtotal-value').value=subtotal.toFixed(2);document.getElementById('iva-value').value=iva.toFixed(2);document.getElementById('discount-value').value=discount.toFixed(2);document.getElementById('retention-base-value').value=taxableBase.toFixed(2);document.getElementById('retention-value').value=retention.toFixed(2);document.getElementById('total-value').value=total.toFixed(2)}
document.addEventListener('change',event=>{if(event.target.matches('.product')){const option=event.target.selectedOptions[0];event.target.closest('.sale-line').querySelector('.price').value=option?.dataset.price||0}calculate()});
document.addEventListener('input',event=>{if(event.target.closest('.sale-line'))calculate()});
calculate();
</script>
</body>
</html>
