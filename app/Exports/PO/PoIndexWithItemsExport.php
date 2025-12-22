<?php

namespace App\Exports\PO;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PoIndexWithItemsExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private Collection $pos,
        private array $meta = [],
        private string $dateCol = 'created_at',
        private ?Company $company = null // ✅ tambah company
    ) {}

    private array $groupRows = [];
    private array $poTotalRows = [];
    private ?int $tableHeaderRow = null;

    // ✅ buat styling header company + title tetap dinamis
    private ?int $companyStartRow = null;
    private ?int $companyEndRow   = null;
    private ?int $titleRow        = null;

    public function array(): array
    {
        $rows = [];
        $rowNo = 0;

        // Kolom A..Q = 17 kolom
        $cols = 17;

        $push = function(array $r) use (&$rows, &$rowNo, $cols) {
            $rows[] = array_pad($r, $cols, null);
            $rowNo++;
            return $rowNo; // 1-based row index
        };

        // ✅ COMPANY HEADER (KOP) — ditambah, tapi tabel & isi export tetap sama
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

            $push([]); // spasi setelah kop
        }

        // ===== header export lu (tetap) =====
        $this->titleRow = $push(['PURCHASE ORDERS EXPORT (INDEX + ITEMS)']);

        // filters bisa string/array (biar aman, nggak ngubah logic lu)
        $filtersVal = $this->meta['filters'] ?? '-';
        if (is_array($filtersVal)) {
            $filtersVal = http_build_query($filtersVal);
        }

        $push(['Generated at', now()->format('d/m/Y H:i')]);
        $push(['Filters', (string)$filtersVal]);
        $push([]);

        $this->tableHeaderRow = $push([
            'PO Code','PO Date','Supplier','Status','Approval',
            'Product Code','Product','Warehouse',
            'Qty','Unit Price','Disc Type','Disc Value','Line Total',
            'PO Subtotal','PO Discount','PO Grand Total','GR Count'
        ]);

        $sumSubtotal = 0;
        $sumDiscount = 0;
        $sumGrand    = 0;

        foreach ($this->pos as $po) {
            $poCode   = (string)($po->po_code ?? '-');
            $poDate   = $this->safeDate($po->{$this->dateCol} ?? $po->created_at ?? null);
            $status   = (string)($po->status ?? '-');
            $approval = (string)($po->approval_status ?: 'draft');
            $supplier = $this->supplierLabel($po);

            // baris pembuka (group PO)
            $groupRow = $push([
                "PO: {$poCode}",
                "Date: {$poDate}",
                "Supplier: {$supplier}",
                "Status: {$status}",
                "Approval: {$approval}",
            ]);
            $this->groupRows[] = $groupRow;

            $poSubtotal = (float)($po->subtotal ?? 0);
            $poDiscount = (float)($po->discount_total ?? 0);
            $poGrand    = (float)($po->grand_total ?? max(0, $poSubtotal - $poDiscount));
            $grCount    = (int)($po->gr_count ?? 0);

            $sumSubtotal += $poSubtotal;
            $sumDiscount += $poDiscount;
            $sumGrand    += $poGrand;

            $items = $po->items ?? collect();

            if ($items->isEmpty()) {
                $push([
                    $poCode, $poDate, $supplier, $status, $approval,
                    '-', '-', '-', 0, 0, '-', 0, 0,
                    null, null, null, null
                ]);
            } else {
                foreach ($items as $it) {
                    $pCode = (string)($it->product->product_code ?? '');
                    $pName = (string)($it->product->name ?? '-');
                    $wh    = (string)($it->warehouse?->warehouse_name ?? 'Central Stock');

                    $qty   = (int)($it->qty_ordered ?? 0);
                    $unit  = (float)($it->unit_price ?? 0);

                    $discType = (string)($it->discount_type ?? '-');
                    $discVal  = (float)($it->discount_value ?? 0);

                    $lineTotal = $it->line_total;
                    if ($lineTotal === null) {
                        $lineTotal = $this->calcLineTotal($qty, $unit, $discType, $discVal);
                    } else {
                        $lineTotal = (float)$lineTotal;
                    }

                    $push([
                        $poCode, $poDate, $supplier, $status, $approval,
                        $pCode, $pName, $wh,
                        $qty, $unit, $discType, $discVal, $lineTotal,
                        null, null, null, null
                    ]);
                }
            }

            // total per PO
            $totalRow = $push([
                null,null,null,null,null,
                null,null,null,
                null,null,null,null,
                'TOTAL PO',
                $poSubtotal,
                $poDiscount,
                $poGrand,
                $grCount
            ]);
            $this->poTotalRows[] = $totalRow;

            $push([]);
        }

        $push([]);
        $grandRow = $push([
            'EXPORT SUMMARY', null,null,null,null,
            null,null,null,
            null,null,null,null,
            'GRAND TOTAL',
            $sumSubtotal,
            $sumDiscount,
            $sumGrand,
            null
        ]);
        $this->poTotalRows[] = $grandRow;

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // ✅ COMPANY HEADER STYLE (center + merge)
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

                    // garis bawah kop
                    $sheet->getStyle("A{$e}:Q{$e}")
                        ->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // ✅ Judul (sekarang dinamis, gak selalu row 1)
                if ($this->titleRow) {
                    $t = $this->titleRow;
                    $sheet->mergeCells("A{$t}:Q{$t}");
                    $sheet->getStyle("A{$t}")->getFont()->setBold(true)->setSize(14);
                }

                // Header tabel
                if ($this->tableHeaderRow) {
                    $hdr = $this->tableHeaderRow;
                    $sheet->freezePane('A'.($hdr + 1));
                    $sheet->setAutoFilter("A{$hdr}:Q{$hdr}");

                    $sheet->getStyle("A{$hdr}:Q{$hdr}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$hdr}:Q{$hdr}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Border (tetap)
                $sheet->getStyle("A{$this->tableHeaderRow}:Q{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Format angka (tetap)
                foreach (['I','J','L','M','N','O','P','Q'] as $col) {
                    $sheet->getStyle("{$col}1:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }

                // Group row (merge) (tetap)
                foreach ($this->groupRows as $r) {
                    $sheet->mergeCells("A{$r}:Q{$r}");
                    $sheet->getStyle("A{$r}:Q{$r}")->getFont()->setBold(true);
                }

                // Total rows bold (tetap)
                foreach ($this->poTotalRows as $r) {
                    $sheet->getStyle("M{$r}:Q{$r}")->getFont()->setBold(true);
                }
            }
        ];
    }

    private function supplierLabel($po): string
    {
        $names = collect();

        if (!empty($po->supplier?->name)) $names->push($po->supplier->name);

        foreach (($po->items ?? []) as $it) {
            $s = $it->product?->supplier?->name ?? null;
            if ($s) $names->push($s);
        }

        $names = $names->filter()->unique()->values();

        if ($names->isEmpty()) return '-';
        if ($names->count() === 1) return $names->first();
        return $names->first().' + '.($names->count() - 1).' supplier';
    }

    private function calcLineTotal(int $qty, float $unit, string $discType, float $discValue): float
    {
        $base = $qty * $unit;
        $t = strtolower(trim($discType));

        $disc = 0.0;
        if ($t === 'percent') {
            $disc = $base * min(max($discValue, 0), 100) / 100;
        } elseif ($t === 'amount') {
            $disc = min(max($discValue, 0), $base);
        }

        return max($base - $disc, 0);
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
}
