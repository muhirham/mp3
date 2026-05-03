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

class WarehouseSettlementExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private Collection $settlements,
        private string $title = 'Warehouse Settlement History Report',
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
            'Deposit Date',
            'Warehouse',
            'Depositor Admin',
            'Total Cash',
            'Total Transfer',
            'Grand Total',
            'Proof Status',
            'Submission Time'
        ];

        $no = 1;
        $grandCash = 0;
        $grandTf = 0;

        foreach ($this->settlements as $s) {
            $total = $s->total_cash_amount + $s->total_transfer_amount;
            $grandCash += $s->total_cash_amount;
            $grandTf += $s->total_transfer_amount;

            $rows[] = [
                $no++,
                $s->settlement_date->format('d/m/Y'),
                $s->warehouse->warehouse_name ?? $s->warehouse->name ?? '-',
                $s->admin->name ?? '-',
                $s->total_cash_amount,
                $s->total_transfer_amount,
                $total,
                $s->proof_path ? 'Available' : 'Not Available',
                $s->created_at->format('d/m/Y H:i')
            ];
        }

        $rows[] = [
            'GRAND TOTAL',
            '', '', '',
            $grandCash,
            $grandTf,
            $grandCash + $grandTf,
            '', ''
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

                // Number Formatting
                $sheet->getStyle("E5:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
                
                // Alignments
                $sheet->getStyle("A5:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H5:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Grand Total Style
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');

                // Column Widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setAutoSize(true);
                $sheet->getColumnDimension('C')->setAutoSize(true);
                $sheet->getColumnDimension('D')->setAutoSize(true);
                $sheet->getColumnDimension('E')->setWidth(18);
                $sheet->getColumnDimension('F')->setWidth(18);
                $sheet->getColumnDimension('G')->setWidth(20);
                $sheet->getColumnDimension('H')->setWidth(15);
                $sheet->getColumnDimension('I')->setAutoSize(true);
            }
        ];
    }
}
