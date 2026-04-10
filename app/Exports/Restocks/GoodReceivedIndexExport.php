<?php

namespace App\Exports\Restocks;

use App\Models\RestockReceipt;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class GoodReceivedIndexExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $q          = $this->filters['q'] ?? '';
        $supplierId = $this->filters['supplier_id'] ?? '';
        $warehouseId= $this->filters['warehouse_id'] ?? '';
        $grType     = $this->filters['gr_type'] ?? '';
        $dateFrom   = $this->filters['date_from'] ?? '';
        $dateTo     = $this->filters['date_to'] ?? '';

        $query = DB::table('restock_receipts as r')
            ->leftJoin('products as p', 'p.id', '=', 'r.product_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.received_by')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
            ->leftJoin('suppliers as spo', 'spo.id', '=', 'po.supplier_id') // Supplier dari Header PO
            ->leftJoin('suppliers as sp', 'sp.id', '=', 'p.supplier_id')   // Supplier dari Master Produk (Fallback)
            ->leftJoin('request_restocks as rr', 'rr.id', '=', 'r.request_id')
            ->leftJoin('purchase_order_items as poi', function($join) {
                $join->on('poi.purchase_order_id', '=', 'r.purchase_order_id')
                     ->on('poi.product_id', '=', 'r.product_id');
            });

        // Filters
        if ($q) {
            $like = '%' . $q . '%';
            $query->where(function($sub) use ($like) {
                $sub->where('r.code', 'like', $like)
                    ->orWhere('p.name', 'like', $like)
                    ->orWhere('p.product_code', 'like', $like)
                    ->orWhere('po.po_code', 'like', $like);
            });
        }

        if ($supplierId)  $query->where('r.supplier_id', $supplierId);
        if ($warehouseId) $query->where('r.warehouse_id', $warehouseId);
        if ($grType)      $query->where('r.gr_type', $grType);
        
        if ($dateFrom)    $query->whereDate('r.received_at', '>=', $dateFrom);
        if ($dateTo)      $query->whereDate('r.received_at', '<=', $dateTo);

        return $query->select([
            'r.code as gr_code',
            'r.received_at',
            'r.gr_type',
            'po.po_code',
            'rr.code as rr_code',
            'w.warehouse_name',
            DB::raw('COALESCE(s.name, spo.name, sp.name, "-") as supplier_name'),
            'p.product_code',
            'p.name as product_name',
            'r.qty_requested',
            'r.qty_good',
            'r.qty_damaged',
            DB::raw('COALESCE(poi.unit_price, rr.cost_per_item, p.purchasing_price, 0) as unit_price'),
            'u.name as receiver_name',
            'r.notes'
        ])
        ->orderBy('r.received_at', 'desc')
        ->get();
    }

    public function headings(): array
    {
        return [
            'GR Code',
            'Date Received',
            'Type',
            'Source Ref',
            'Warehouse',
            'Supplier',
            'Product Code',
            'Product Name',
            'Qty Ordered',
            'Qty Good',
            'Qty Damaged',
            'Unit Price',
            'Total Ordered',
            'Total Payable (Good Only)',
            'Receiver',
            'Notes'
        ];
    }

    public function map($r): array
    {
        $type = match($r->gr_type) {
            'po' => 'PO',
            'request_stock' => 'Request Stock',
            'gr_transfer' => 'Transfer',
            'gr_return' => 'Return',
            default => 'Other'
        };

        $sourceRef = $r->po_code ?? ($r->rr_code ?? '-');
        $price = (float)($r->unit_price ?? 0);
        
        $totalOrdered = (int)$r->qty_requested * $price;
        $totalPayable = (int)$r->qty_good * $price;

        return [
            $r->gr_code,
            $r->received_at ? Carbon::parse($r->received_at)->format('Y-m-d H:i') : '-',
            $type,
            $sourceRef,
            $r->warehouse_name ?? 'Central',
            $r->supplier_name,
            $r->product_code,
            $r->product_name,
            $r->qty_requested,
            $r->qty_good,
            $r->qty_damaged,
            $price,
            $totalOrdered,
            $totalPayable,
            $r->receiver_name,
            $r->notes
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
