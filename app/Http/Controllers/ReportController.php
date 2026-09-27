<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function data(Request $request): array
    {
        $filters = validator([
            'from' => $request->query('from', now()->startOfMonth()->toDateString()),
            'to' => $request->query('to', now()->toDateString()),
            'type' => $request->query('type', 'all'),
        ], [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['required', 'in:all,purchases,sales,support,payroll'],
        ], [
            'from.required' => 'Indica la fecha inicial.',
            'from.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'to.required' => 'Indica la fecha final.',
            'to.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'type.required' => 'Selecciona un módulo.',
            'type.in' => 'El módulo seleccionado no es válido.',
        ])->validate();

        $from = $filters['from'];
        $to = $filters['to'];
        $type = $filters['type'];
        $rows = collect();

        if (in_array($type, ['all', 'purchases'], true)) {
            Purchase::whereBetween('purchase_date', [$from, $to])->get()->each(fn ($item) => $rows->push(['date' => $item->purchase_date->toDateString(), 'type' => 'Compra', 'document' => $item->invoice_number, 'third_party' => $item->provider, 'total' => $item->total_pagar]));
        }
        if (in_array($type, ['all', 'sales'], true)) {
            Sale::whereBetween('sale_date', [$from, $to])->get()->each(fn ($item) => $rows->push(['date' => $item->sale_date->toDateString(), 'type' => 'Venta', 'document' => $item->invoice_number, 'third_party' => $item->customer_name, 'total' => $item->total]));
        }
        if (in_array($type, ['all', 'support'], true)) {
            SupportDocument::whereBetween('document_date', [$from, $to])->with('supplier')->get()->each(fn ($item) => $rows->push(['date' => $item->document_date->toDateString(), 'type' => 'Documento soporte', 'document' => $item->consecutive, 'third_party' => $item->supplier->name, 'total' => $item->total]));
        }
        if (in_array($type, ['all', 'payroll'], true)) {
            PayrollRun::whereBetween('payment_date', [$from, $to])->get()->each(fn ($item) => $rows->push(['date' => $item->payment_date->toDateString(), 'type' => 'Nómina', 'document' => $item->period, 'third_party' => 'Empleados', 'total' => $item->total_net]));
        }

        return compact('from', 'to', 'type', 'rows');
    }

    public function index(Request $request)
    {
        $report = $this->data($request);

        return view('reports.index', $report);
    }

    public function excel(Request $request)
    {
        $report = $this->data($request);
        $lines = [['Fecha', 'Tipo', 'Documento', 'Tercero', 'Total']];
        foreach ($report['rows'] as $row) {
            $lines[] = [$row['date'], $row['type'], $row['document'], $row['third_party'], number_format($row['total'], 2, '.', '')];
        }
        $content = collect($lines)->map(fn ($line) => collect($line)->map(fn ($value) => '"'.str_replace('"', '""', $value).'"')->implode(';'))->implode("\r\n");

        return response("\xEF\xBB\xBF".$content, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="reporte-'.$report['from'].'-'.$report['to'].'.csv"']);
    }

    public function pdf(Request $request)
    {
        return view('reports.print', $this->data($request));
    }
}
