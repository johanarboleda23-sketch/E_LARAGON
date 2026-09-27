<?php

namespace App\Services;

use App\Exports\ExcelExport;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

class ExcelReportService
{
    /**
     * Export financial reports to Excel format.
     *
     * @return Response
     */
    public function exportToExcel(array $data, string $fileName)
    {
        return ExcelFacade::download(new ExcelExport($data), $fileName);
    }
}
