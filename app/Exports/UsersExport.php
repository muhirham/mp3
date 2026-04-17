<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

class UsersExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    protected $users;

    public function __construct(Collection $users)
    {
        $this->users = $users;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->users->map(function($u) {
            return [
                $u->id,
                $u->name,
                $u->username,
                $u->email,
                $u->phone ?? '-',
                $u->position ?? '-',
                $u->roles->pluck('name')->implode(', '),
                $u->warehouse?->warehouse_name ?? '-',
                ucfirst($u->status),
                $u->created_at ? $u->created_at->format('Y-m-d H:i') : '-',
                $u->updated_at ? $u->updated_at->format('Y-m-d H:i') : '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Full Name',
            'Username',
            'Email Address',
            'Phone',
            'Position',
            'Roles',
            'Warehouse',
            'Status',
            'Created At',
            'Updated At',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Bold header
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastColumn = $sheet->getHighestColumn();
                $range = "A1:{$lastColumn}{$lastRow}";

                // Kasih Border ke semua cell
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                
                // Aktifkan Auto Filter di baris pertama
                $sheet->setAutoFilter("A1:{$lastColumn}1");
            },
        ];
    }
}
