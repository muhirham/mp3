<?php

namespace App\Exports\StockAdjustments;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StockAdjustmentIndexWithItemsExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private Collection $adjs,
        private array $meta = [],
        private string $dateCol = 'adj_date',
        private ?Company $company = null
    ) {}

    private array $groupRows = [];
    private array $noteRows = [];
    private array $docTotalRows = [];
    private ?int $tableHeaderRow = null;

    private ?int $companyStartRow = null;
    private ?int $companyEndRow   = null;
    private ?int $titleRow        = null;

    public function array(): array
    {
        $rows  = [];
        $rowNo = 0;

        // Kolom A..Q = 17 kolom
        $cols = 17;

        $push = function(array $r) use (&$rows, &$rowNo, $cols) {
            $rows[] = array_pad($r, $cols, null);
            $rowNo++;
            return $rowNo; // 1-based
        };

        // ✅ COMPANY HEADER (KOP)
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

            $this->companyStartRow = $push([$legal ?: '-']);
            $push([$addr]);
            $push([$cityProv]);
            $push([$contact]);
            $this->companyEndRow = $push([$npwp]);

            $push([]); // spasi
        }

        // TITLE
        $this->titleRow = $push(['STOCK ADJUSTMENTS EXPORT (INDEX + ITEMS)']);

        $filtersVal = $this->meta['filters'] ?? '-';
        if (is_array($filtersVal)) $filtersVal = http_build_query($filtersVal);

        $push(['Generated at', now()->format('d/m/Y H:i')]);
        $push(['Filters', (string)$filtersVal]);
        $push([]);

        // TABLE HEADER
        $this->tableHeaderRow = $push([
            'Adj Code',
            'Adj Date',
            'Warehouse',
            'Mode Stok',
            'Mode Update',
            'Dibuat Oleh',
            'Jam Input',
            'Product Code',
            'Product',
            'Qty Before',
            'Qty After',
            'Diff',
            'Harga Beli Before',
            'Harga Beli After',
            'Harga Jual Before',
            'Harga Jual After',
            'Catatan Item',
        ]);

        $sumDocs     = 0;
        $sumLines    = 0;
        $sumDiffNet  = 0;
        $sumQtyB     = 0;
        $sumQtyA     = 0;

        foreach ($this->adjs as $adj) {
            $sumDocs++;

            $code = (string)($adj->adj_code ?? '-');
            $date = $this->safeDate($adj->{$this->dateCol} ?? $adj->created_at ?? null);
            $wh   = $adj->warehouse_id ? (string)($adj->warehouse?->warehouse_name ?? '-') : 'Stock Central';

            $modeStok   = (string)($adj->stock_scope_mode ?? '-');
            $modeUpdate = (string)($adj->price_update_mode ?? '-');
            $creator    = (string)($adj->creator?->name ?? '-');
            $jamInput   = $this->safeTime($adj->created_at ?? null);

            // Cek mode update untuk penentuan kolom Harga/Stok
            $hasStock    = in_array($modeUpdate, ['stock', 'stock_purchase_selling'], true);
            $hasPurchase = in_array($modeUpdate, ['purchase', 'purchase_selling', 'stock_purchase_selling'], true);
            $hasSelling  = in_array($modeUpdate, ['selling', 'purchase_selling', 'stock_purchase_selling'], true);

            // GROUP ROW (merge)
            $groupRow = $push([
                "ADJ: {$code} | Date: {$date} | WH: {$wh} | Scope: {$modeStok} | Update: {$modeUpdate} | By: {$creator} | Jam: {$jamInput}"
            ]);
            $this->groupRows[] = $groupRow;

            // Notes global (optional)
            $notes = trim((string)($adj->notes ?? ''));
            if ($notes !== '') {
                $noteRow = $push(["Notes: {$notes}"]);
                $this->noteRows[] = $noteRow;
            }

            $items = $adj->items ?? collect();

            if ($items->isEmpty()) {
                $push([
                    $code, $date, $wh, $modeStok, $modeUpdate, $creator, $jamInput,
                    '-', '-', '-', '-', '-', '-', '-', '-', '-', '-'
                ]);
            } else {
                $docQtyB = 0;
                $docQtyA = 0;
                $docDiff = 0;
                $lineCount = 0;

                foreach ($items as $it) {
                    $sumLines++;
                    $lineCount++;

                    $pCode = (string)($it->product?->product_code ?? '');
                    $pName = (string)($it->product?->name ?? '-');

                    $qb = (int)($it->qty_before ?? 0);
                    $qa = (int)($it->qty_after ?? 0);
                    $df = $it->qty_diff;
                    $df = ($df === null) ? ($qa - $qb) : (int)$df;

                    // Logika Dinamis (jika doc tidak update domain tsb, isi '-')
                    $valQB = $hasStock ? $qb : '-';
                    $valQA = $hasStock ? $qa : '-';
                    $valDF = $hasStock ? $df : '-';

                    $pb = $hasPurchase ? $it->purchase_price_before : '-';
                    $pa = $hasPurchase ? $it->purchase_price_after  : '-';
                    $sb = $hasSelling  ? $it->selling_price_before  : '-';
                    $sa = $hasSelling  ? $it->selling_price_after   : '-';

                    $itemNote = (string)($it->notes ?? '');

                    if ($hasStock) {
                        $docQtyB += $qb;
                        $docQtyA += $qa;
                        $docDiff += $df;
                    }

                    $push([
                        $code, $date, $wh, $modeStok, $modeUpdate, $creator, $jamInput,
                        $pCode,
                        $pName,
                        $valQB,
                        $valQA,
                        $valDF,
                        $pb, $pa, $sb, $sa,
                        $itemNote,
                    ]);
                }

                if ($hasStock) {
                    $sumQtyB    += $docQtyB;
                    $sumQtyA    += $docQtyA;
                    $sumDiffNet += $docDiff;
                }

                // TOTAL DOC (Hapus penjumlah stok absolut, ganti info jumlah item)
                $totalRow = $push([
                    null,null,null,null,null,null,null,
                    null,
                    "{$lineCount} Item(s)",
                    '-', // Qty Before (dihapus penjumlahannya)
                    '-', // Qty After  (dihapus penjumlahannya)
                    $hasStock ? $docDiff : '-', // Cuma Diff yang boleh ditotal (net impact)
                    null,null,null,null,
                    null
                ]);
                $this->docTotalRows[] = $totalRow;
            }

            $push([]);
        }

        $push([]);
        // GRAND SUMMARY (Filter stok saja yang dijumlah)
        $grandRow = $push([
            'EXPORT SUMMARY', null,null,null,null,null,null,
            null,
            'TOTAL NET DIFF',
            '-',
            '-',
            $sumDiffNet,
            null,null,null,null,
            null
        ]);
        $this->docTotalRows[] = $grandRow;

        // info tambahan (biar jelas)
        $push([]);
        $push(['Total Documents', $sumDocs]);
        $push(['Total Line Items', $sumLines]);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // ✅ COMPANY HEADER STYLE + MERGE
                if ($this->companyStartRow && $this->companyEndRow) {
                    $s = $this->companyStartRow;
                    $e = $this->companyEndRow;

                    for ($r = $s; $r <= $e; $r++) {
                        $sheet->mergeCells("A{$r}:Q{$r}");
                    }

                    $sheet->getStyle("A{$s}:Q{$e}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    $sheet->getStyle("A{$s}")->getFont()->setBold(true)->setSize(14);
                    $sheet->getStyle("A".($s+1).":A{$e}")->getFont()->setSize(10);

                    $sheet->getStyle("A{$e}:Q{$e}")
                        ->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // ✅ TITLE MERGE
                if ($this->titleRow) {
                    $t = $this->titleRow;
                    $sheet->mergeCells("A{$t}:Q{$t}");
                    $sheet->getStyle("A{$t}")->getFont()->setBold(true)->setSize(14);
                }

                // HEADER TABLE
                if ($this->tableHeaderRow) {
                    $hdr = $this->tableHeaderRow;

                    $sheet->freezePane('A'.($hdr + 1));
                    $sheet->setAutoFilter("A{$hdr}:Q{$hdr}");

                    $sheet->getStyle("A{$hdr}:Q{$hdr}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$hdr}:Q{$hdr}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // BORDER TABLE (dari header sampai bawah)
                if ($this->tableHeaderRow) {
                    $hdr = $this->tableHeaderRow;
                    $sheet->getStyle("A{$hdr}:Q{$lastRow}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // FORMAT ANGKA: Qty & Harga
                foreach (['J','K','L','M','N','O','P'] as $col) {
                    $sheet->getStyle("{$col}1:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }

                // GROUP ROW merge + bold
                foreach ($this->groupRows as $r) {
                    $sheet->mergeCells("A{$r}:Q{$r}");
                    $sheet->getStyle("A{$r}:Q{$r}")->getFont()->setBold(true);
                }

                // NOTE ROW merge + italic
                foreach ($this->noteRows as $r) {
                    $sheet->mergeCells("A{$r}:Q{$r}");
                    $sheet->getStyle("A{$r}:Q{$r}")->getFont()->setItalic(true);
                }

                // TOTAL rows bold (kolom I..L biar keliatan)
                foreach ($this->docTotalRows as $r) {
                    $sheet->getStyle("I{$r}:L{$r}")->getFont()->setBold(true);
                }
            }
        ];
    }

    private function safeDate($value): string
    {
        if (empty($value)) return '-';
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string)$value;
        }
    }

    private function safeTime($value): string
    {
        if (empty($value)) return '-';
        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            return '-';
        }
    }
}
