<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('warehouse.view'), 403);

        $warehouses = Warehouse::orderBy('id')->get();

        $nextCode = Warehouse::nextCode();

        return view('admin.masterdata.warehouses', compact('warehouses', 'nextCode'));
    }

    public function store(Request $r)
    {
        abort_unless(auth()->user()->hasPermission('warehouse.create'), 403);

        if (auth()->user()->hasRole('warehouse')) {
            abort(403);
        }

        $data = $r->validate([
            'warehouse_code' => ['nullable','string','max:50','unique:warehouses,warehouse_code'],
            'warehouse_name' => ['required','string','max:150'],
            'address'        => ['nullable','string','max:255'],
            'note'           => ['nullable','string','max:255'],
        ]);

        if (empty($data['warehouse_code'])) {
            $data['warehouse_code'] = Warehouse::nextCode();
        }

        $row = Warehouse::create($data);

        return response()->json([
            'message' => 'created',
            'row' => $row
        ], 201);
    }

    public function update(Request $r, Warehouse $warehouse)
    {
        abort_unless(auth()->user()->hasPermission('warehouse.update'), 403);

        $me = auth()->user();

        // warehouse cuma boleh edit gudangnya sendiri
        if ($me->hasRole('warehouse') && $me->warehouse_id !== $warehouse->id) {
            abort(403);
        }

        $data = $r->validate([
            'warehouse_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses','warehouse_code')->ignore($warehouse->id)
            ],
            'warehouse_name' => ['required','string','max:150'],
            'address'        => ['nullable','string','max:255'],
            'note'           => ['nullable','string','max:255'],
        ]);

        $warehouse->update($data);

        return response()->json([
            'message' => 'updated',
            'row' => $warehouse->fresh()
        ]);
    }

    public function destroy(Warehouse $warehouse)
    {
        abort_unless(auth()->user()->hasPermission('warehouse.delete'), 403);

        if (auth()->user()->hasRole('warehouse')) {
            abort(403);
        }

        // --- PROTEKSI KERAS: CEK STOK & TRANSAKSI ---

        // 1. Cek Stok (stock_levels)
        $totalStock = (int) \Illuminate\Support\Facades\DB::table('stock_levels')
            ->where('owner_type', 'warehouse')
            ->where('owner_id', $warehouse->id)
            ->sum('quantity');

        if ($totalStock > 0) {
            return response()->json([
                'message' => "Gudang tidak bisa dihapus karena masih memiliki stok aktif ({$totalStock} unit)."
            ], 422);
        }

        // 2. Cek Transaksi Terkait (Gudang ini tidak boleh ada di histori manapun agar DB tetap clean)
        // Kita cek tabel-tabel utama
        $hasRestock  = \Illuminate\Support\Facades\DB::table('request_restocks')->where('warehouse_id', $warehouse->id)->exists();
        $hasPOItem   = \Illuminate\Support\Facades\DB::table('purchase_order_items')->where('warehouse_id', $warehouse->id)->exists();
        $hasTransfer = \Illuminate\Support\Facades\DB::table('warehouse_transfers')
            ->where('source_warehouse_id', $warehouse->id)
            ->orWhere('destination_warehouse_id', $warehouse->id)
            ->exists();
        $hasHandover = \Illuminate\Support\Facades\DB::table('sales_handovers')->where('warehouse_id', $warehouse->id)->exists();

        if ($hasRestock || $hasPOItem || $hasTransfer || $hasHandover) {
            return response()->json([
                'message' => "Gudang tidak bisa dihapus karena sudah memiliki histori transaksi (Restock/PO/Transfer/Handover)."
            ], 422);
        }

        $warehouse->delete();

        return response()->noContent();
    }

    public function exportSeeder()
    {
        abort_unless(auth()->user()->hasPermission('warehouse.view'), 403);
 
        $rows = Warehouse::orderBy('id')->get();
        $filename = "warehouses_seeder_" . date('Ymd_His') . ".csv";
 
        $callback = function() use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'warehouse_code', 'warehouse_name', 'address', 'note']);
 
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->id,
                    $r->warehouse_code,
                    $r->warehouse_name,
                    $r->address,
                    $r->note
                ]);
            }
            fclose($handle);
        };
 
        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}