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
        'sale' => ['label' => 'Venta (Factura)', 'model' => Sale::class, 'field' => 'invoice_number', 'route' => 'sales.show'],
        'purchase' => ['label' => 'Compra (Factura)', 'model' => Purchase::class, 'field' => 'invoice_number', 'route' => 'purchases.show'],
        'support_document' => ['label' => 'Documento soporte', 'model' => SupportDocument::class, 'field' => 'consecutive', 'route' => 'support-documents.show'],
        'payroll' => ['label' => 'Nómina (Periodo)', 'model' => PayrollRun::class, 'field' => 'period', 'route' => 'payroll.show'],
        'voucher' => ['label' => 'Comprobante contable', 'model' => AccountingVoucher::class, 'field' => 'consecutive', 'route' => null],
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
            // El documento existe pero aún no se ha contabilizado: llevamos al usuario al propio
            // documento (donde puede contabilizarlo con el botón "Contabilizar ahora") en vez de
            // detenernos con un mensaje sin salida.
            $documentUrl = $config['route'] ? route($config['route'], $record) : null;

            return response()->json([
                'found' => false,
                'message' => 'El documento existe pero aún no tiene un comprobante contable asociado.',
                'document_url' => $documentUrl,
            ], 404);
        }

        return response()->json(['found' => true, 'voucher_id' => $voucherId]);
    }

    /**
     * Search for documents by consecutive and return a list of matching vouchers.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consecutive' => ['required', 'string', 'max:100'],
        ]);

        $results = [];

        foreach (self::MODULES as $key => $config) {
            $records = $config['model']::query()
                ->where($config['field'], $data['consecutive'])
                ->get();

            foreach ($records as $record) {
                $voucherId = $key === 'voucher' ? $record->id : $record->accounting_voucher_id;
                $results[] = [
                    'module' => $key,
                    'voucher_id' => $voucherId,
                    'label' => $config['label'] ?? $key,
                    'has_voucher' => (bool) $voucherId,
                    'document_url' => (! $voucherId && $config['route']) ? route($config['route'], $record) : null,
                ];
            }
        }

        if (empty($results)) {
            return response()->json(['found' => false, 'message' => 'No se encontró ningún documento con ese consecutivo.'], 404);
        }

        return response()->json(['found' => true, 'results' => $results]);
    }
}
