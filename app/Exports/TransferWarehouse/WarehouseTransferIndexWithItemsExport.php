<?php

namespace App\Exports\TransferWarehouse;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class WarehouseTransferIndexWithItemsExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private Collection $transfers,
        private array $meta = [],
        private ?Company $company = null
    ) {}

    private array $groupRows = [];
    private array $totalRows = [];
    private ?int $tableHeaderRow = null;

    private ?int $companyStartRow = null;
    private ?int $companyEndRow   = null;
    private ?int $titleRow        = null;

    public function array(): array
    {
        $rows = [];
        $rowNo = 0;

        $cols = 12; // A..L

        $push = function(array $r) use (&$rows, &$rowNo, $cols) {
            $rows[] = array_pad($r, $cols, null);
            $rowNo++;
            return $rowNo;
        };

        /* ================= KOP SURAT ================= */
        if ($this->company) {
            $c = $this->company;

            $this->companyStartRow = $push([$c->legal_name ?? $c->name ?? '-']);
            $push([$c->address ?? '']);
            $push([trim(($c->city ?? '') . ' - ' . ($c->province ?? ''))]);
            $push(['Tel: '.$c->phone.' | '.$c->email.' | '.$c->website]);
            $this->companyEndRow = $push([$c->tax_number ? 'NPWP: '.$c->tax_number : '']);

            $push([]);
        }

        /* ================= TITLE ================= */
        $this->titleRow = $push(['WAREHOUSE TRANSFER EXPORT (INDEX + ITEMS)']);
        $push(['Generated at', now()->format('d/m/Y H:i')]);
        foreach ($this->meta['filters'] ?? [] as $k => $v) {
            $push(["Filter {$k}", $v]);
        }

        $push([]);

        /* ================= TABLE HEADER ================= */
        $this->tableHeaderRow = $push([
            'Transfer Code','Date','From Warehouse','To Warehouse','Status',
            'Product Code','Product',
            'Qty','Unit Cost','Line Total',
            'Transfer Total',''
        ]);

        $grandTotal = 0;

        foreach ($this->transfers as $tr) {
            $code   = $tr->transfer_code ?? '-';
            $date   = $this->fmtDate($tr->created_at);
            $from   = $tr->sourceWarehouse->warehouse_name ?? '-';
            $to     = $tr->destinationWarehouse->warehouse_name ?? '-';
            $status = strtoupper($tr->status ?? '-');

            /* === GROUP HEADER === */
            $g = $push([
                "TRANSFER: {$code}",
                "DATE: {$date}",
                "FROM: {$from}",
                "TO: {$to}",
                "STATUS: {$status}",
            ]);
            $this->groupRows[] = $g;

            $transferTotal = 0;

            foreach ($tr->items as $it) {
            $qty  = (int) ($it->qty_transfer ?? 0);
            $cost = (float) $it->unit_cost;
            $line = $qty * $cost;
                $transferTotal += $line;

                $push([
                    $code, $date, $from, $to, $status,
                    $it->product->product_code ?? '-',
                    $it->product->name ?? '-',
                    (int)$it->qty_transfer,
                    (float)$it->unit_cost,
                    $line,
                    null,
                    null
                ]);
            }

            $grandTotal += $transferTotal;

            /* === TOTAL PER TRANSFER === */
            $t = $push([
                null,null,null,null,null,
                null,null,null,null,
                'TOTAL TRANSFER',
                $transferTotal,
                null
            ]);
            $this->totalRows[] = $t;

            $push([]);
        }

        /* ================= SUMMARY ================= */
        $push([]);
        $s = $push([
            'EXPORT SUMMARY', null,null,null,null,
            null,null,null,null,
            'GRAND TOTAL',
            $grandTotal,
            null
        ]);
        $this->totalRows[] = $s;

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                /* KOP STYLE */
                if ($this->companyStartRow && $this->companyEndRow) {
                    for ($r = $this->companyStartRow; $r <= $this->companyEndRow; $r++) {
                        $sheet->mergeCells("A{$r}:L{$r}");
                    }

                    $sheet->getStyle("A{$this->companyStartRow}:L{$this->companyEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("A{$this->companyStartRow}")
                        ->getFont()->setBold(true)->setSize(14);

                    $sheet->getStyle("A{$this->companyEndRow}:L{$this->companyEndRow}")
                        ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
                }

                /* TITLE */
                $sheet->mergeCells("A{$this->titleRow}:L{$this->titleRow}");
                $sheet->getStyle("A{$this->titleRow}")->getFont()->setBold(true)->setSize(14);

                /* HEADER */
                $hdr = $this->tableHeaderRow;
                $sheet->freezePane('A'.($hdr + 1));
                $sheet->setAutoFilter("A{$hdr}:L{$hdr}");
                $sheet->getStyle("A{$hdr}:L{$hdr}")->getFont()->setBold(true);

                /* BORDER */
                $sheet->getStyle("A{$hdr}:L{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                /* NUMBER FORMAT */
                foreach (['H','I','J','K'] as $c) {
                    $sheet->getStyle("{$c}1:{$c}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }

                /* GROUP ROW */
                foreach ($this->groupRows as $r) {
                    $sheet->mergeCells("A{$r}:L{$r}");
                    $sheet->getStyle("A{$r}:L{$r}")->getFont()->setBold(true);
                }

                /* TOTAL ROW */
                foreach ($this->totalRows as $r) {
                    $sheet->getStyle("J{$r}:L{$r}")->getFont()->setBold(true);
                }
            }
        ];
    }

    private function fmtDate($v): string
    {
        try {
            return Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return '-';
        }
    }
}
