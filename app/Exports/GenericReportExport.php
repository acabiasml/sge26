<?php

namespace App\Exports;

use App\Support\Reports\ReportDefinition;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class GenericReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private readonly ReportDefinition $report) {}

    public function collection(): Collection
    {
        return $this->report->rows;
    }

    public function headings(): array
    {
        return $this->report->headings;
    }

    public function title(): string
    {
        return mb_substr($this->report->title, 0, 31);
    }
}
