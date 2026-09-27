<?php

namespace App\Exports;

use App\Models\FinancialMovement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FinancialReportExport implements FromCollection, WithHeadings
{
    /**
     * Prepare the collection of financial movements for export.
     *
     * @return Collection
     */
    public function collection()
    {
        return FinancialMovement::all();
    }

    /**
     * Define the headings for the exported file.
     */
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
