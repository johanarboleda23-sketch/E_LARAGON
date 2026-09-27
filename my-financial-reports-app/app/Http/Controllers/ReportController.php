<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use App\Services\ReportService;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    protected $reportService;

    protected $pdfReportService;

    protected $excelReportService;

    public function __construct(ReportService $reportService, PdfReportService $pdfReportService, ExcelReportService $excelReportService)
    {
        $this->reportService = $reportService;
        $this->pdfReportService = $pdfReportService;
        $this->excelReportService = $excelReportService;
    }

    public function index(ReportFilterRequest $request)
    {
        $filters = $request->validated();
        $reports = $this->reportService->generateReports($filters);

        return view('reports.index', compact('reports'));
    }

    public function exportPdf(ReportFilterRequest $request)
    {
        $filters = $request->validated();
        $pdf = $this->pdfReportService->generatePdf($filters);

        return Response::make($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="financial_report.pdf"',
        ]);
    }

    public function exportExcel(ReportFilterRequest $request)
    {
        $filters = $request->validated();

        return $this->excelReportService->generateExcel($filters);
    }
}
