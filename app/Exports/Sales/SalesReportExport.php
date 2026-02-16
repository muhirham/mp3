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

       /* ===== KOP SURAT ===== */
        if ($this->company) {
            $c = $this->company;

            $legal = (string)($c->legal_name ?? $c->name ?? '');
            $addr  = (string)($c->address ?? '');

            $cityProv = trim(
                ((string)($c->city ?? '')) .
                (((string)($c->city ?? '') !== '' && (string)($c->province ?? '') !== '') ? ' - ' : '') .
                ((string)($c->province ?? ''))
            );

            $contactParts = [];
            if (!empty($c->phone))   $contactParts[] = 'Tel: '.$c->phone;
            if (!empty($c->email))   $contactParts[] = 'Email: '.$c->email;
            if (!empty($c->website)) $contactParts[] = $c->website;
            $contact = implode(' | ', $contactParts);

            $npwp = !empty($c->tax_number) ? 'NPWP: '.$c->tax_number : '';

            $this->companyStartRow = $this->push([$legal ?: '-'], $rows);
            $this->push([$addr], $rows);
            $this->push([$cityProv], $rows);
            $this->push([$contact], $rows);
            $this->companyEndRow = $this->push([$npwp], $rows);

            $this->push([], $rows); // spasi setelah kop
        }



        /* ===== TITLE ===== */
            $title = match ($this->view) {
            'sales' => 'SALES REPORT (REKAP PER SALES)',
            'daily' => 'SALES REPORT (REKAP PER HARI)',
            default => 'SALES REPORT (DETAIL HANDOVER)',
        };

        $this->titleRow = $this->push([$title], $rows);

        $this->push(['Generated at', now()->format('d/m/Y H:i')], $rows);

        foreach ($this->meta['filters'] ?? [] as $k => $v) {
            $this->push(["Filter {$k}", $v], $rows);
        }

        $this->push([], $rows);

        // 🔥 SWITCH VIEW
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

        $this->push([
            "HANDOVER: {$h->code}",
            "DATE: ".optional($h->handover_date)->format('d/m/Y'),
            "WAREHOUSE: ".optional($h->warehouse)->warehouse_name,
            "SALES: ".optional($h->sales)->name,
            "STATUS: ".strtoupper($h->status),
        ], $rows);

        $handoverTotal = 0;
        $nilaiDibawaTotal = 0;
        $qtyReturnTotal = 0;

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
            $qtyReturnTotal += $qtyReturn;


            $this->push([
                $h->code,
                optional($h->handover_date)->format('d/m/Y'),
                optional($h->warehouse)->warehouse_name,
                optional($h->sales)->name,
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

        // =============================
        // RINGKASAN (CUMA SEKALI PER HDO)
        // =============================

        $cash     = (int) ($h->cash_amount ?? 0);
        $transfer = (int) ($h->transfer_amount ?? 0);
        $totalSetor = $cash + $transfer;

        $nilaiDibawa = (int) ($h->total_dispatched_amount ?? $nilaiDibawaTotal);
        $nilaiTerjual = (int) ($h->total_sold_amount ?? $handoverTotal);

        $sisaStock = max(0, $nilaiDibawa - $nilaiTerjual);
        $selisihSetor = $nilaiTerjual - $totalSetor;

        $this->push([], $rows);

        $this->push(['RINGKASAN HANDOVER'], $rows);

        $this->push([null,null,null,null,null,null,null,null,null,null,null,
            'Nilai Dibawa', $nilaiDibawa
        ], $rows);

        $this->push([null,null,null,null,null,null,null,null,null,null,null,
            'Nilai Terjual', $nilaiTerjual
        ], $rows);

        $this->push([null,null,null,null,null,null,null,null,null,null,null,
            'Total Barang Kembali', $qtyReturnTotal
        ], $rows);

        $this->push([null,null,null,null,null,null,null,null,null,null,null,
            'Sisa Stok (Estimasi)', $sisaStock
        ], $rows);

        $this->push([null,null,null,null,null,null,null,null,null,null,null,
            'Setor Tunai', $cash
        ], $rows);

        $this->push([null,null,null,null,null,null,null,null,null,null,null,
            'Setor Transfer', $transfer
        ], $rows);

        $this->push([null,null,null,null,null,null,null,null,null,null,null,
            'Total Setoran', $totalSetor
        ], $rows);

        $this->push([null,null,null,null,null,null,null,null,null,null,null,
            'Total Diskon', $selisihSetor
        ], $rows);

        $this->push([], $rows);

            
        $grandTotal += $handoverTotal;



            // TOTAL PER HANDOVER
            $this->push([
            null,null,null,null,null,
            null,null,null,null,null,null,
            'TOTAL HANDOVER',
            $handoverTotal,
            null
        ], $rows);


            $this->push([], $rows);
        }

        /* ===== GRAND TOTAL ===== */
        $this->push([], $rows);
        $this->push([
            'EXPORT SUMMARY',null,null,null,null,
            null,null,null,null,
            'GRAND TOTAL',
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


            /* ================= KOP SURAT ================= */
            if ($this->companyStartRow && $this->companyEndRow) {
                for ($r = $this->companyStartRow; $r <= $this->companyEndRow; $r++) {
                    $sheet->mergeCells("A{$r}:{$lastColumnLetter}{$r}");

                }

                // center semua teks kop
                $sheet->getStyle("A{$this->companyStartRow}:{$lastColumnLetter}{$this->companyEndRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // nama perusahaan
                $sheet->getStyle("A{$this->companyStartRow}")
                    ->getFont()
                    ->setBold(true)
                    ->setSize(14);

                // 🔥 GARIS PEMISAH KOP (INI YANG TADI KURANG)
                $sheet->getStyle("A{$this->companyEndRow}:O{$this->companyEndRow}")
                    ->getBorders()
                    ->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN);
            }

            /* ================= TITLE ================= */
            if ($this->titleRow) {
                $sheet->mergeCells("A{$this->titleRow}:{$lastColumnLetter}{$this->titleRow}");
                $sheet->getStyle("A{$this->titleRow}")
                    ->getFont()
                    ->setBold(true)
                    ->setSize(13);
            }

            /* ================= TABLE HEADER ================= */
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
            foreach (['J','K','L','M','N'] as $c) {
                $sheet->getStyle("{$c}1:{$c}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
                }
            }
        ];
    }

        private function buildSalesView(array $rows): array
        {
            $this->push([], $rows);

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
            $this->push([], $rows);

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
