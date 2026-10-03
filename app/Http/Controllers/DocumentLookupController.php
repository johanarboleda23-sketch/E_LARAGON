<?php

namespace App\Http\Controllers;

use App\Models\AccountingVoucher;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentLookupController extends Controller
{
    public const MODULES = [
        'sale' => ['label' => 'Venta (Factura)', 'model' => Sale::class, 'field' => 'invoice_number'],
        'purchase' => ['label' => 'Compra (Factura)', 'model' => Purchase::class, 'field' => 'invoice_number'],
        'support_document' => ['label' => 'Documento soporte', 'model' => SupportDocument::class, 'field' => 'consecutive'],
        'payroll' => ['label' => 'Nómina (Periodo)', 'model' => PayrollRun::class, 'field' => 'period'],
        'voucher' => ['label' => 'Comprobante contable', 'model' => AccountingVoucher::class, 'field' => 'consecutive'],
    ];

    public function find(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'in:'.implode(',', array_keys(self::MODULES))],
            'consecutive' => ['required', 'string', 'max:100'],
        ]);

        $config = self::MODULES[$data['module']];
        $record = $config['model']::query()->where($config['field'], $data['consecutive'])->first();

        if (! $record) {
            return response()->json(['found' => false, 'message' => 'No se encontró ningún documento con ese consecutivo.'], 404);
        }

        $voucherId = $data['module'] === 'voucher' ? $record->id : $record->accounting_voucher_id;

        if (! $voucherId) {
            return response()->json(['found' => false, 'message' => 'El documento existe pero aún no tiene un comprobante contable asociado.'], 404);
        }

        return response()->json(['found' => true, 'voucher_id' => $voucherId]);
    }
}
