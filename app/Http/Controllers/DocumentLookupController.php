<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\AccountingVoucher;
use App\Models\CommercialDocument;
use App\Models\InventoryMovement;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
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
            'module' => ['required', 'string', 'in:'.implode(',', [...array_keys(self::MODULES), 'pos', 'egreso', 'recibo_caja', 'social_security', 'closing', 'third_party', 'customer_credit_note', 'customer_debit_note', 'supplier_credit_note', 'supplier_debit_note'])],
            'consecutive' => ['required', 'string', 'max:100'],
        ]);

        $module = $data['module'];
        if ($module === 'social_security') {
            $voucher = AccountingVoucher::query()->where('voucher_type', 'seguridad_social')->where('consecutive', $data['consecutive'])->first();
            if ($voucher) {
                return response()->json(['found' => true, 'voucher_id' => $voucher->id]);
            }
            $run = PayrollRun::query()->where('period', $data['consecutive'])->latest('id')->first();

            return $run
                ? response()->json(['found' => true, 'document_url' => route('payroll.show', $run)])
                : response()->json(['found' => false, 'message' => 'No se encontró el comprobante o periodo de seguridad social.'], 404);
        }
        if ($module === 'third_party') {
            $thirdParty = ThirdParty::query()->where(function ($query) use ($data): void {
                $query->where('name', $data['consecutive'])->orWhere('document', $data['consecutive']);
            })->first();

            return $thirdParty
                ? response()->json(['found' => true, 'document_url' => route('third-parties.show', $thirdParty)])
                : response()->json(['found' => false, 'message' => 'No se encontró el tercero.'], 404);
        }

        if ($module === 'closing') {
            $period = AccountingPeriod::query()->where('period_key', $data['consecutive'])->where('status', 'closed')->first();

            return $period
                ? response()->json(['found' => true, 'document_url' => route('period-closing.index', ['year' => substr($period->period_key, 0, 4)])])
                : response()->json(['found' => false, 'message' => 'No se encontró el cierre.'], 404);
        }

        $noteRoutes = [
            'customer_credit_note' => 'commercial-documents.customer-credit-notes',
            'customer_debit_note' => 'commercial-documents.customer-debit-notes',
            'supplier_credit_note' => 'commercial-documents.supplier-credit-notes',
            'supplier_debit_note' => 'commercial-documents.supplier-debit-notes',
        ];
        if (isset($noteRoutes[$module])) {
            $note = CommercialDocument::query()->where('document_type', $module)->where('consecutive', $data['consecutive'])->first();

            return $note
                ? response()->json(['found' => true, 'document_url' => route($noteRoutes[$module], ['preview' => $note->id])])
                : response()->json(['found' => false, 'message' => 'No se encontró la nota.'], 404);
        }

        $config = self::MODULES[match ($module) {
            'pos' => 'sale',
            'egreso', 'recibo_caja' => 'voucher',
            default => $module,
        }];
        $query = $config['model']::query()->where($config['field'], $data['consecutive']);
        if ($module === 'pos') {
            $hasPosMovement = InventoryMovement::query()->where('reason', 'Venta POS '.$data['consecutive'])->exists();
            if (! str_starts_with($data['consecutive'], 'POS-') && ! $hasPosMovement) {
                return response()->json(['found' => false, 'message' => 'No se encontró la factura de punto de venta.'], 404);
            }
        } elseif (in_array($module, ['egreso', 'recibo_caja'], true)) {
            $query->where('voucher_type', $module);
        }
        $record = $query->first();

        if (! $record) {
            return response()->json(['found' => false, 'message' => 'No se encontró ningún documento con ese consecutivo.'], 404);
        }

        $voucherId = in_array($module, ['voucher', 'egreso', 'recibo_caja'], true) ? $record->id : $record->accounting_voucher_id;

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
