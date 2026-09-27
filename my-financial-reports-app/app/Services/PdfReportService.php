<?php

namespace App\Services;

use App\Models\FinancialMovement; // Assuming you are using a package like barryvdh/laravel-dompdf
use PDF;

class PdfReportService
{
    protected $financialMovements;

    public function __construct(FinancialMovement $financialMovements)
    {
        $this->financialMovements = $financialMovements;
    }

    public function generateReport(array $filters)
    {
        $data = $this->financialMovements->filter($filters); // Assuming you have a filter method

        $pdf = PDF::loadView('reports.pdf', compact('data'));

        return $pdf->download('financial_report.pdf');
    }
}
