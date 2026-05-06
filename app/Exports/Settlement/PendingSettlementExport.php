<?php

namespace App\Exports\Settlement;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PendingSettlementExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private Collection $pendingData,
        private string $title = 'Pending Warehouse Deposit Report',
        private array $filters = []
    ) {}

    public function array(): array
    {
        $rows = [];
        
        // Header
        $rows[] = [$this->title]; // Row 1
        
        $dateStart = $this->filters['date_start'] ?? 'Beginning';
        $dateEnd = $this->filters['date_end'] ?? 'Today';
        $whName = $this->filters['warehouse_name'] ?? 'All Warehouses';
        
        $rows[] = ["Period: {$dateStart} to {$dateEnd}  |  Warehouse: {$whName}"]; // Row 2
        $rows[] = ['Printed at: ' . now()->format('d/m/Y H:i')]; // Row 3

        $rows[] = [ // Row 4
            'No',
            'Operational Date',
            'Warehouse',
            'Sales Person',
            'HDO Count',
            'Total Cash (Safe)',
            'Total Transfer (POS)',
            'Grand Total',
            'Last Updated'
        ];

        $no = 1;
        $grandCash = 0;
        $grandTf = 0;

        foreach ($this->pendingData as $p) {
            $total = $p->total_cash + $p->total_transfer;
            $grandCash += $p->total_cash;
            $grandTf += $p->total_transfer;

            $rows[] = [
                $no++,
                \Carbon\Carbon::parse($p->handover_date)->format('d/m/Y'),
                $p->warehouse->warehouse_name ?? $p->warehouse->name ?? '-',
                $p->sales->name ?? '-',
                $p->total_handovers,
                $p->total_cash,
                $p->total_transfer,
                $total,
                \Carbon\Carbon::parse($p->last_updated)->format('d/m/Y H:i')
            ];
        }

        $rows[] = [
            'TOTAL PENDING FUNDS',
            '', '', '', '',
            $grandCash,
            $grandTf,
            $grandCash + $grandTf,
            ''
        ];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = 'I';
                
                // Set Row Heights
                $sheet->getDefaultRowDimension()->setRowHeight(20);
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(4)->setRowHeight(25);

                // Style Title (Row 1)
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
                $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Style Meta (Row 2 & 3)
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle('A2:A3')->getFont()->setItalic(true)->setSize(10);

                // Table Headers (Row 4)
                $headerRange = "A4:{$lastCol}4";
                $sheet->getStyle($headerRange)->getFont()->setBold(true);
                $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Borders for data rows (A5 to last row)
                $sheet->getStyle("A5:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Number Formatting (F, G, H)
                $sheet->getStyle("F5:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
                
                // Alignments
                $sheet->getStyle("A5:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E5:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Grand Total Style
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');

                // Column Widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setAutoSize(true);
                $sheet->getColumnDimension('C')->setAutoSize(true);
                $sheet->getColumnDimension('D')->setAutoSize(true);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(20);
                $sheet->getColumnDimension('G')->setWidth(20);
                $sheet->getColumnDimension('H')->setWidth(22);
                $sheet->getColumnDimension('I')->setAutoSize(true);
            }
        ];
    }
}
