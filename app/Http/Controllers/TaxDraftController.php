<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxDraftController extends Controller
{
    private const TYPES = [
        'iva' => 'IVA',
        'retencion' => 'Retención en la fuente',
        'ica' => 'Industria y Comercio (ICA)',
        'renta' => 'Impuesto de renta',
    ];

    public function index()
    {
        $types = self::TYPES;

        return view('tax-drafts.index', compact('types'));
    }

    public function show(Request $request, string $type)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $from = $request->date('from') ?? now()->startOfYear();
        $to = $request->date('to') ?? now();
        $company = Company::find(session('company_id'));

        $data = match ($type) {
            'iva' => $this->ivaDraft($from, $to),
            'retencion' => $this->retentionDraft($from, $to),
            'ica' => $this->icaDraft($from, $to, $company),
            'renta' => $this->incomeTaxDraft($from, $to, $company),
        };

        return view('tax-drafts.show', [
            'type' => $type,
            'label' => self::TYPES[$type],
            'from' => $from,
            'to' => $to,
            'company' => $company,
            'data' => $data,
        ]);
    }

    private function salesSubtotal($from, $to): float
    {
        return (float) Sale::query()->whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)->sum('subtotal')
            + (float) SupportDocument::query()->whereDate('document_date', '>=', $from)->whereDate('document_date', '<=', $to)->sum('subtotal');
    }

    private function ivaDraft($from, $to): array
    {
        $ivaGeneradoVentas = (float) Sale::query()->whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)->sum('iva_total');
        $ivaGeneradoDocSoporte = (float) SupportDocument::query()->whereDate('document_date', '>=', $from)->whereDate('document_date', '<=', $to)->sum('iva_total');
        $ivaDescontable = (float) Purchase::query()->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)->sum('iva_total');
        $ivaGenerado = $ivaGeneradoVentas + $ivaGeneradoDocSoporte;
        $saldo = $ivaGenerado - $ivaDescontable;

        return [
            'iva_generado_ventas' => $ivaGeneradoVentas,
            'iva_generado_doc_soporte' => $ivaGeneradoDocSoporte,
            'iva_generado' => $ivaGenerado,
            'iva_descontable' => $ivaDescontable,
            'saldo_a_pagar' => max($saldo, 0),
            'saldo_a_favor' => max(-$saldo, 0),
        ];
    }

    private function retentionDraft($from, $to): array
    {
        $retefuenteCompras = (float) Purchase::query()->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)->sum('retefuente');
        $autorretencionDocSoporte = (float) SupportDocument::query()->whereDate('document_date', '>=', $from)->whereDate('document_date', '<=', $to)->sum('retention_total');
        $retencionSufridaVentas = (float) Sale::query()->whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)->sum('retention_total');

        return [
            'retefuente_practicada_compras' => $retefuenteCompras,
            'autorretencion_documentos_soporte' => $autorretencionDocSoporte,
            'total_a_declarar_y_pagar' => $retefuenteCompras + $autorretencionDocSoporte,
            'retencion_que_nos_practicaron_ventas' => $retencionSufridaVentas,
        ];
    }

    private function icaDraft($from, $to, ?Company $company): array
    {
        $ingresosGravados = $this->salesSubtotal($from, $to);
        $rate = (float) ($company?->ica_rate_per_thousand ?? 0);
        $impuesto = $ingresosGravados * ($rate / 1000);

        return [
            'ingresos_gravados' => $ingresosGravados,
            'tasa_por_mil' => $rate,
            'impuesto_a_cargo' => $impuesto,
            'tasa_configurada' => $rate > 0,
        ];
    }

    private function incomeTaxDraft($from, $to, ?Company $company): array
    {
        $ingresos = $this->salesSubtotal($from, $to);
        $costosCompras = (float) Purchase::query()->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)->sum('subtotal');
        $gastosContables = (float) DB::table('accounting_voucher_lines')
            ->join('accounting_vouchers', 'accounting_vouchers.id', '=', 'accounting_voucher_lines.accounting_voucher_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'accounting_voucher_lines.chart_of_account_id')
            ->whereDate('accounting_vouchers.voucher_date', '>=', $from)
            ->whereDate('accounting_vouchers.voucher_date', '<=', $to)
            ->whereIn('chart_of_accounts.class', ['5', '6', '7'])
            ->whereNull('accounting_vouchers.deleted_at')
            ->sum('accounting_voucher_lines.debit');

        $utilidad = $ingresos - $costosCompras - $gastosContables;
        $rate = (float) ($company?->income_tax_rate_percentage ?? 35);
        $impuesto = max($utilidad, 0) * ($rate / 100);

        return [
            'ingresos' => $ingresos,
            'costos_compras' => $costosCompras,
            'gastos_contables' => $gastosContables,
            'utilidad_antes_de_impuesto' => $utilidad,
            'tasa_renta' => $rate,
            'impuesto_estimado' => $impuesto,
        ];
    }
}
