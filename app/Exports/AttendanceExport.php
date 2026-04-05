<?php

namespace App\Exports;

use App\Models\Attendance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected Collection $records) {}

    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Time',
            'Student Name',
            'Student Number',
            'Section',
            'Subject',
            'Status',
        ];
    }

    /**
     * @param  Attendance  $row
     */
    public function map($row): array
    {
        return [
            $row->scanned_at->toDateString(),
            $row->scanned_at->format('h:i A'),
            $row->student?->name ?? 'N/A',
            $row->student?->student_number ?? 'N/A',
            $row->student?->section ?? 'N/A',
            $row->subject?->name ?? 'N/A',
            $row->status,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
