<?php

namespace App\Exports;

use App\Models\FinancialMovement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExcelExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return FinancialMovement::all();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Account ID',
            'Amount',
            'Type',
            'Date',
            'Description',
        ];
    }
}
