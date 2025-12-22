<?php

namespace App\Exports\Restocks;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RestockIndexWithItemsExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function __construct(
        private Collection $rows,
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

        // A..L = 12 kolom
        $cols = 12;

        $push = function(array $r) use (&$rows, &$rowNo, $cols) {
            $rows[] = array_pad($r, $cols, null);
            $rowNo++;
            return $rowNo; // 1-based
        };

        // COMPANY HEADER
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

            $push([]);
        }

        $this->titleRow = $push(['RESTOCK REQUEST EXPORT (INDEX + ITEMS)']);

        $filtersVal = $this->meta['filters'] ?? '-';
        if (is_array($filtersVal)) $filtersVal = http_build_query($filtersVal);

        $push(['Generated at', now()->format('d/m/Y H:i')]);
        $push(['Filters', (string)$filtersVal]);
        $push([]);

        $this->tableHeaderRow = $push([
            'RR Code','Date','Warehouse','Requester','Status',
            'Product Code','Product','Supplier',
            'Qty Req','Qty Rcv','Remaining','Note'
        ]);

        $sumReq = 0;
        $sumRcv = 0;

        $grouped = $this->rows->groupBy('code');

        foreach ($grouped as $code => $items) {
            $first = $items->first();

            $date = $first?->created_at ? $this->safeDate($first->created_at) : '-';
            $wh   = (string)($first->warehouse_name ?? '-');
            $req  = (string)($first->requester_name ?? '-');
            $st   = (string)($first->status ?? 'pending');

            $groupRow = $push([
                "RR: {$code}",
                "Date: {$date}",
                "Warehouse: {$wh}",
                "Requester: {$req}",
                "Status: {$st}",
            ]);
            $this->groupRows[] = $groupRow;

            $docReq = 0;
            $docRcv = 0;

            foreach ($items as $it) {
                $qtyReq = (int)($it->qty_req ?? 0);
                $qtyRcv = (int)($it->qty_rcv ?? 0);
                $rem    = max($qtyReq - $qtyRcv, 0);

                $docReq += $qtyReq;
                $docRcv += $qtyRcv;

                $push([
                    (string)$code,
                    $this->safeDate($it->created_at ?? null),
                    (string)($it->warehouse_name ?? '-'),
                    (string)($it->requester_name ?? '-'),
                    (string)($it->status ?? 'pending'),
                    (string)($it->product_code ?? ''),
                    (string)($it->product_name ?? '-'),
                    (string)($it->supplier_name ?? '-'),
                    $qtyReq,
                    $qtyRcv,
                    $rem,
                    (string)($it->note ?? ''),
                ]);
            }

            $sumReq += $docReq;
            $sumRcv += $docRcv;

            $totalRow = $push([
                null,null,null,null,null,null,null,
                'TOTAL RR',
                $docReq,
                $docRcv,
                max($docReq - $docRcv, 0),
                null
            ]);
            $this->totalRows[] = $totalRow;

            $push([]);
        }

        $push([]);
        $grandRow = $push([
            'EXPORT SUMMARY', null,null,null,null,null,null,
            'GRAND TOTAL',
            $sumReq,
            $sumRcv,
            max($sumReq - $sumRcv, 0),
            null
        ]);
        $this->totalRows[] = $grandRow;

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // COMPANY HEADER merge + center
                if ($this->companyStartRow && $this->companyEndRow) {
                    $s = $this->companyStartRow;
                    $e = $this->companyEndRow;

                    for ($r = $s; $r <= $e; $r++) {
                        $sheet->mergeCells("A{$r}:L{$r}");
                    }

                    $sheet->getStyle("A{$s}:L{$e}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    $sheet->getStyle("A{$s}")->getFont()->setBold(true)->setSize(14);
                    $sheet->getStyle("A".($s+1).":A{$e}")->getFont()->setSize(10);

                    $sheet->getStyle("A{$e}:L{$e}")
                        ->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // TITLE
                if ($this->titleRow) {
                    $t = $this->titleRow;
                    $sheet->mergeCells("A{$t}:L{$t}");
                    $sheet->getStyle("A{$t}")->getFont()->setBold(true)->setSize(14);
                }

                // TABLE HEADER
                if ($this->tableHeaderRow) {
                    $hdr = $this->tableHeaderRow;
                    $sheet->freezePane('A'.($hdr + 1));
                    $sheet->setAutoFilter("A{$hdr}:L{$hdr}");

                    $sheet->getStyle("A{$hdr}:L{$hdr}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$hdr}:L{$hdr}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // BORDER
                $sheet->getStyle("A{$this->tableHeaderRow}:L{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // NUMBER FORMAT qty
                foreach (['I','J','K'] as $col) {
                    $sheet->getStyle("{$col}1:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }

                // GROUP ROW merge + bold
                foreach ($this->groupRows as $r) {
                    $sheet->mergeCells("A{$r}:L{$r}");
                    $sheet->getStyle("A{$r}:L{$r}")->getFont()->setBold(true);
                }

                // TOTAL rows bold
                foreach ($this->totalRows as $r) {
                    $sheet->getStyle("H{$r}:K{$r}")->getFont()->setBold(true);
                }
            }
        ];
    }

    private function safeDate($value): string
    {
        if (empty($value)) return '-';
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return (string)$value;
        }
    }
}
