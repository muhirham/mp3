<?php

namespace App\Exports\Sales;

use App\Models\Company;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SalesReportExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private Collection $handovers,
        private string $view,
        private array $meta = [],
        private ?Company $company = null
    ) {}

    private int $row = 0;
    private int $lastColumnIndex = 1;

    private ?int $companyStartRow = null;
    private ?int $companyEndRow   = null;
    private ?int $titleRow        = null;
    private ?int $tableHeaderRow  = null;

    private function push(array $r, array &$rows): int
    {
        $rows[] = array_pad($r, 14, null);
        return ++$this->row;
    }

    public function array(): array
    {
        $rows = [];
        $this->row = 0;

        // 🔥 OPSI B: LANGSUNG HEADER TABEL (BARIS 1)
        if ($this->view === 'sales') {
            return $this->buildSalesView($rows);
        }

        if ($this->view === 'daily') {
            return $this->buildDailyView($rows);
        }

        /* ===== TABLE HEADER ===== */
        $this->tableHeaderRow = $this->push([
            'Handover Code',
            'Tanggal',
            'Warehouse',
            'Sales',
            'Status',
            'Product Code',
            'Product',
            'Qty Dibawa',
            'Qty Terjual',
            'Qty Kembali',
            'Harga Asli',
            'Diskon',
            'Harga After Disc',
            'Nilai Dibawa',
            'Nilai Terjual'
        ], $rows);
        $this->lastColumnIndex = 15;

        $grandTotal = 0;

        foreach ($this->handovers as $h) {

            $handoverTotal = 0;
            $nilaiDibawaTotal = 0;

            foreach ($h->items as $it) {

                $qtyStart = (int) ($it->qty_start ?? 0);
                $qtySold  = (int) ($it->qty_sold ?? 0);

                $priceOri = (int) ($it->unit_price ?? 0);
                $disc     = (int) ($it->discount_per_unit ?? 0);
                $priceNet = max(0, $priceOri - $disc);

                $nilaiDibawa  = $qtyStart * $priceNet;
                $nilaiTerjual = $qtySold * $priceNet;
                $qtyReturn = max(0, $qtyStart - $qtySold);

                $nilaiDibawaTotal += $nilaiDibawa;
                $handoverTotal    += $nilaiTerjual;

                // 🔥 REPEAT DIMENSIONS ON EACH ROW
                $this->push([
                    $h->code,
                    optional($h->handover_date)->format('d/m/Y'),
                    optional($h->warehouse)->warehouse_name ?? '-',
                    optional($h->sales)->name ?? '-',
                    strtoupper($h->status),

                    $it->product->product_code ?? '-',
                    $it->product->name ?? '-',

                    $qtyStart,
                    $qtySold,
                    $qtyReturn,
                    $priceOri,
                    $disc,
                    $priceNet,
                    $nilaiDibawa,
                    $nilaiTerjual,
                ], $rows);
            }

            $grandTotal += $handoverTotal;
        }

        /* ===== GRAND TOTAL ===== */
        $this->push([], $rows);
        $this->push([
            'GRAND TOTAL',
            null, null, null, null,
            null, null, null, null, null,
            null, null, null, null,
            $grandTotal
        ], $rows);

        return $rows;
    }
    


    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $sheet   = $e->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastColumnLetter = Coordinate::stringFromColumnIndex($this->lastColumnIndex);

                /* ================= TABLE HEADER (ROW 1) ================= */
                if ($this->tableHeaderRow) {
                    $hdr = $this->tableHeaderRow;

                    $sheet->freezePane('A' . ($hdr + 1));
                    $sheet->setAutoFilter("A{$hdr}:{$lastColumnLetter}{$hdr}");

                    $sheet->getStyle("A{$hdr}:{$lastColumnLetter}{$hdr}")
                        ->getFont()
                        ->setBold(true);
                }

                /* ================= BORDER TABLE ================= */
                if ($this->tableHeaderRow) {
                    $sheet->getStyle("A{$this->tableHeaderRow}:{$lastColumnLetter}{$lastRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                /* ================= NUMBER FORMAT ================= */
                // K: Qty Kembali, L: Harga Asli, M: Diskon, N: Harga After Disc, O: Nilai Dibawa, P: Nilai Terjual
                // Karena kita baris 1 header, kolom-kolom numeric kita ada di H-O (Index 8-15)
                foreach (['H','I','J','K','L','M','N','O'] as $c) {
                    $sheet->getStyle("{$c}1:{$c}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // Center Align for Qty columns
                foreach (['H','I','J'] as $c) {
                    $sheet->getStyle("{$c}2:{$c}{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        ];
    }

        private function buildSalesView(array $rows): array
        {
            $this->tableHeaderRow = $this->push([
                '#',
                'Sales',
                'Warehouse',
                'HDO',
                'Total Dibawa',
                'Total Terjual (Closed)',
                'Total Setor'
            ], $rows);

                $this->lastColumnIndex = 7;

                $grouped = $this->handovers->groupBy(function ($h) {
                return $h->sales_id ?? 'unknown';
            });


            $no = 1;

            foreach ($grouped as $salesId => $list) {

                $first = $list->first();

                $salesName = optional($first->sales)->name ?? '-';
                $warehouse = optional($first->warehouse)->warehouse_name ?? '-';

                $totalHdo = $list->count();

                $totalDibawa = $list->sum(function ($h) {
                    return (int) ($h->total_dispatched_amount ?? 0);
                });

                $totalTerjual = $list->sum(function ($h) {
                    return (int) ($h->total_sold_amount ?? 0);
                });

                $totalSetor = $list->sum(function ($h) {
                    return (int) $h->cash_amount + (int) $h->transfer_amount;
                });

                $this->push([
                    $no++,
                    $salesName,
                    $warehouse,
                    $totalHdo,
                    $totalDibawa,
                    $totalTerjual,
                    $totalSetor
                ], $rows);
            }

            return $rows;
        }


        private function buildDailyView(array $rows): array
        {
            $this->tableHeaderRow = $this->push([
                'No',
                'Tanggal',
                'Jumlah Handover',
                'Nilai Dibawa',
                'Nilai Terjual',
                'Total Setoran'
            ], $rows);
            $this->lastColumnIndex = 6;

            $grouped = $this->handovers->groupBy(function ($h) {
                return optional($h->handover_date)->format('Y-m-d');
            });

            $no = 1;

            foreach ($grouped as $date => $list) {

                $totalHdo = $list->count();
                $nilaiDibawa = $list->sum('total_dispatched_amount');
                $nilaiTerjual = $list->sum('total_sold_amount');
                $setor = $list->sum(function ($h) {
                    return (int) $h->cash_amount + (int) $h->transfer_amount;
                });

                $this->push([
                    $no++,
                    $date,
                    $totalHdo,
                    $nilaiDibawa,
                    $nilaiTerjual,
                    $setor
                ], $rows);
            }

            return $rows;
        }

}
