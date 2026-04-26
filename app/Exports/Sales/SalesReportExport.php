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
            'Buyer Type',
            'Buyer Name',
            'Status',
            'Product Code',
            'Product',
            'Qty Dibawa',
            'Qty Terjual',
            'Qty Kembali',
            'Harga Asli',
            'Harga NET',
            'Mode Diskon',
            'Diskon',
            'Nilai Dibawa (NET)',
            'Nilai Terjual (NET)'
        ], $rows);
        $this->lastColumnIndex = 18;

        $grandTotal = 0;

        foreach ($this->handovers as $h) {

            $handoverTotal = 0;
            $nilaiDibawaTotal = 0;

            foreach ($h->items as $it) {

                $qtyStart = (int) ($it->qty_start ?? 0);
                $qtySold  = (int) ($it->qty_sold ?? 0);
                $qtyReturn = max(0, $qtyStart - $qtySold);

                $priceOri    = (int) ($it->unit_price ?? 0);
                $discMode    = $it->discount_mode ?? 'unit';
                $discUnit    = (int) ($it->discount_per_unit ?? 0);
                $discFixed   = (int) ($it->discount_fixed_amount ?? 0);
                $discTotal   = (int) ($it->discount_total ?? 0);

                if ($discMode === 'fixed') {
                    // Fixed/bundle: nilai dibawa = total NET (setelah diskon bundle)
                    $nilaiDibawa  = (int) ($it->line_total_after_discount ?? max(0, ($qtyStart * $priceOri) - $discFixed));
                    $nilaiTerjual = (int) ($it->line_total_sold ?? 0);
                    $discDisplay  = $discFixed;   // tampilkan nilai bundle
                    $priceNet     = $qtyStart > 0 ? (int) floor($nilaiDibawa / $qtyStart) : $priceOri;
                } else {
                    // Unit: harga net per unit dikurangi diskon
                    $priceNet     = max(0, $priceOri - $discUnit);
                    $nilaiDibawa  = (int) ($it->line_total_after_discount ?? ($qtyStart * $priceNet));
                    $nilaiTerjual = (int) ($it->line_total_sold ?? ($qtySold * $priceNet));
                    $discDisplay  = $discUnit;
                }

                $nilaiDibawaTotal += $nilaiDibawa;
                $handoverTotal    += $nilaiTerjual;

                // 🔥 REPEAT DIMENSIONS ON EACH ROW
                $this->push([
                    $h->code,
                    optional($h->handover_date)->format('d/m/Y'),
                    optional($h->warehouse)->warehouse_name ?? '-',
                    optional($h->sales)->name ?? '-',
                    strtoupper($h->buyer_type ?? 'sales'),
                    $h->customer_name ?? '-',
                    strtoupper($h->status),

                    $it->product->product_code ?? '-',
                    $it->product->name ?? '-',

                    $qtyStart,
                    $qtySold,
                    $qtyReturn,
                    $priceOri,
                    $priceNet,               // Harga NET
                    strtoupper($discMode),   // Mode Diskon
                    $discDisplay,            // Diskon (per unit atau bundle)
                    $nilaiDibawa,            // Nilai Dibawa (NET)
                    $nilaiTerjual,           // Nilai Terjual (NET)
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
                // J: Qty Dibawa, K: Qty Terjual, L: Qty Kembali, M: Harga Asli, N: Harga NET, P: Diskon, Q: Nilai Dibawa, R: Nilai Terjual
                foreach (['J','K','L','M','N','P','Q','R'] as $c) {
                    $sheet->getStyle("{$c}1:{$c}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // Center Align for Qty columns
                foreach (['J','K','L'] as $c) {
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
