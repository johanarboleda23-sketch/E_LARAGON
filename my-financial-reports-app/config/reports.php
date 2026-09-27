<?php

use App\Models\ReportTemplate;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;

return [
    'default_format' => 'pdf', // Formato predeterminado para los reportes
    'export_formats' => [
        'pdf' => [
            'class' => PdfReportService::class,
            'options' => [
                'paper_size' => 'A4',
                'orientation' => 'portrait',
            ],
        ],
        'excel' => [
            'class' => ExcelReportService::class,
            'options' => [
                'sheet_name' => 'Reportes Financieros',
                'include_headers' => true,
            ],
        ],
    ],
    'report_templates' => [
        'default' => ReportTemplate::class,
        'available' => [
            'summary',
            'detailed',
            'custom',
        ],
    ],
    'permissions' => [
        'view_reports' => 'view financial reports',
        'export_reports' => 'export financial reports',
    ],
];
