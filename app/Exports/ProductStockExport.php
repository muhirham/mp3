<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\Company;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ProductStockExport implements FromArray, WithEvents, ShouldAutoSize
{
    private ?int $companyStartRow = null;
    private ?int $companyEndRow   = null;
    private ?int $titleRow        = null;
    private ?int $tableHeaderRow  = null;
    private array $dataRows       = [];
    private int $totalCols        = 0;
    private string $lastColLetter = 'A';

    protected $warehouseId;
    protected $startDate;
    protected $endDate;
    protected $warehouses;
    protected $company;

    public function __construct($warehouseId = null, $startDate = null, $endDate = null)
    {
        $this->warehouseId = $warehouseId;
        $this->startDate   = $startDate;
        $this->endDate     = $endDate;
        
        // Load active company
        $this->company = Company::where('is_active', 1)->first();

        // If showing all warehouses, load all warehouses for dynamic columns
        if (!$this->warehouseId) {
            $this->warehouses = Warehouse::orderBy('id')->get();
        }
    }

    public function array(): array
    {
        $rows  = [];
        $rowNo = 0;

        // Base Columns = 12 (ID, Code, Name, Type, Category, Supplier, Pkg, Desc, PurchPrice, HPP, SellPrice, MinStock)
        $this->totalCols = 12;

        if ($this->warehouseId) {
            $this->totalCols += 1; // 1 WH Stock
        } else {
            $this->totalCols += count($this->warehouses) + 1; // All WH + 1 Total
        }
        $this->totalCols += 2; // Created At, Status

        $this->lastColLetter = Coordinate::stringFromColumnIndex($this->totalCols);

        $push = function(array $r) use (&$rows, &$rowNo) {
            $rows[] = array_pad($r, $this->totalCols, null);
            $rowNo++;
            return $rowNo; // 1-based index
        };

        // ✅ COMPANY HEADER (KOP)
        if ($this->company) {
            $c = $this->company;

            $legal = (string)($c->legal_name ?? $c->name ?? 'MASTER PRODUCT SYSTEM');
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
            if ($addr !== '') $push([$addr]);
            if ($cityProv !== '') $push([$cityProv]);
            if ($contact !== '') $push([$contact]);
            $this->companyEndRow = $push([$npwp !== '' ? $npwp : '']);

            $push([]); // spasi
        }

        // TITLE
        $this->titleRow = $push(['MASTER PRODUCT & STOCK MATRIX REPORT']);

        // FILTERS
        $warehouseFilterName = 'ALL WAREHOUSES';
        if ($this->warehouseId) {
            $wh = Warehouse::find($this->warehouseId);
            if ($wh) $warehouseFilterName = strtoupper($wh->warehouse_name);
        }

        $dateFilter = 'All Time';
        if ($this->startDate || $this->endDate) {
            $start = $this->startDate ? date('d/m/Y', strtotime($this->startDate)) : 'Awal';
            $end   = $this->endDate ? date('d/m/Y', strtotime($this->endDate)) : 'Akhir';
            $dateFilter = $start . ' s/d ' . $end;
        }

        $push(['Generated at', now()->format('d/m/Y H:i')]);
        $push(['Filter Warehouse', $warehouseFilterName]);
        $push(['Filter Item Created', $dateFilter]);
        $push([]);

        // TABLE HEADER
        $headers = [
            'ID',
            'Product Code',
            'Product Name',
            'Type',
            'Category',
            'Supplier',
            'Package / UOM',
            'Description',
            'Purchasing Price',
            'Standard Cost (HPP)',
            'Selling Price',
            'Minimum Stock',
        ];

        // Kolom Stock
        if ($this->warehouseId) {
            $headers[] = 'Available WH Stock';
        } else {
            foreach ($this->warehouses as $wh) {
                $headers[] = 'Stock ' . $wh->warehouse_name;
            }
            $headers[] = 'Total All Stock';
        }

        $headers[] = 'Created At';
        $headers[] = 'Active Status';

        $this->tableHeaderRow = $push($headers);

        // DATA QUERY
        $query = Product::with(['category', 'supplier', 'package']);
        
        $query->with(['stockLevels' => function ($q) {
            if ($this->warehouseId) {
                $q->where('owner_id', $this->warehouseId);
            }
            $q->where('owner_type', 'warehouse');
        }]);

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        $products = $query->orderBy('product_code')->get();

        foreach ($products as $product) {
            $catName = optional($product->category)->name ?? optional($product->category)->category_name ?? '-';
            $pkgName = optional($product->package)->name ?? optional($product->package)->package_name ?? '-';

            $row = [
                $product->id,
                $product->product_code,
                $product->name,
                ucfirst($product->product_type),
                $catName,
                optional($product->supplier)->name ?? '-',
                $pkgName,
                $product->description,
                (float)$product->purchasing_price,
                (float)$product->standard_cost,
                (float)$product->selling_price,
                (float)$product->stock_minimum,
            ];

            // Data Stock Dinamis
            if ($this->warehouseId) {
                $row[] = (float)$product->stockLevels->sum('quantity');
            } else {
                $totalStock = 0;
                foreach ($this->warehouses as $wh) {
                    $whStock = $product->stockLevels->where('owner_id', $wh->id)->sum('quantity');
                    $row[] = (float)$whStock;
                    $totalStock += $whStock;
                }
                $row[] = (float)$totalStock;
            }

            $row[] = $product->created_at ? $product->created_at->format('Y-m-d H:i:s') : '-';
            $row[] = $product->is_active ? 'Active' : 'Inactive';

            $dataRowIdx = $push($row);
            $this->dataRows[] = $dataRowIdx;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = $this->lastColLetter; // Kolom terakhir dinamis (misal 'Q', 'Z', 'AB')

                // ✅ COMPANY HEADER STYLE + MERGE
                if ($this->companyStartRow && $this->companyEndRow) {
                    $s = $this->companyStartRow;
                    $e = $this->companyEndRow;

                    for ($r = $s; $r <= $e; $r++) {
                        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                    }

                    $sheet->getStyle("A{$s}:{$lastCol}{$e}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    $sheet->getStyle("A{$s}")->getFont()->setBold(true)->setSize(14);
                    $sheet->getStyle("A".($s+1).":A{$e}")->getFont()->setSize(10);

                    $sheet->getStyle("A{$e}:{$lastCol}{$e}")
                        ->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // ✅ TITLE MERGE
                if ($this->titleRow) {
                    $t = $this->titleRow;
                    $sheet->mergeCells("A{$t}:{$lastCol}{$t}");
                    $sheet->getStyle("A{$t}")->getFont()->setBold(true)->setSize(14);
                }

                // HEADER TABLE
                if ($this->tableHeaderRow) {
                    $hdr = $this->tableHeaderRow;

                    $sheet->freezePane('A'.($hdr + 1));
                    $sheet->setAutoFilter("A{$hdr}:{$lastCol}{$hdr}");

                    $sheet->getStyle("A{$hdr}:{$lastCol}{$hdr}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$hdr}:{$lastCol}{$hdr}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);
                }

                // BORDER TABLE (dari header sampai bawah)
                if ($this->tableHeaderRow && $lastRow >= $this->tableHeaderRow) {
                    $hdr = $this->tableHeaderRow;
                    $sheet->getStyle("A{$hdr}:{$lastCol}{$lastRow}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // FORMAT ANGKA: Price, HPP, Min Stock, dan semua Dynamic Stock Kolom
                // purchasing_price (I), std_cost (J), selling (K), min_stock (L) => index 9,10,11,12 di 1-based
                // start format dari index 9 sampai index (totalCols - 2)
                $firstNumColIdx = 9; 
                $lastNumColIdx = $this->totalCols - 2; 
                
                // Menerapkan Number Format (Loop berdasarkan index, ubah ke String/Letter)
                for ($col = $firstNumColIdx; $col <= $lastNumColIdx; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $sheet->getStyle("{$colLetter}1:{$colLetter}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }
            }
        ];
    }
}
