<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DirectSalesController extends Controller
{
    /**
     * Tampilan Halaman POS Gudang
     */
    public function index(Request $request)
    {
        $me = auth()->user();
        $isAdminLike = $me->hasRole(['superadmin', 'admin']);

        // 1. Ambil List Gudang yang diperbolehkan
        if ($isAdminLike) {
            $warehouses = Warehouse::orderBy('warehouse_name')->get();
        } else {
            $warehouses = Warehouse::where('id', $me->warehouse_id)->get();
        }

        // 2. Tentukan Gudang Aktif (Prioritas: Query Param > User Profile > First Warehouse)
        $activeWarehouseId = $request->query('warehouse_id');
        if (!$activeWarehouseId) {
            $activeWarehouseId = $me->warehouse_id ?: ($warehouses->first()?->id);
        }

        $warehouse = $warehouses->firstWhere('id', $activeWarehouseId);
        
        if (!$warehouse) {
            return redirect()->route('dashboard')->with('error', 'Warehouse context not found.');
        }

        // 3. Tanggal (Biar pas ganti gudang gak reset cok!)
        $selectedDate = $request->query('handover_date', date('Y-m-d'));

        // List Produk yang HANYA ADA STOKNYA di gudang terpilih
        $products = Product::whereHas('stockLevels', function($q) use ($activeWarehouseId) {
            $q->where('owner_id', $activeWarehouseId)
              ->where('owner_type', 'warehouse')
              ->where('quantity', '>', 0);
        })->with(['stockLevels' => function($q) use ($activeWarehouseId) {
            $q->where('owner_id', $activeWarehouseId)
              ->where('owner_type', 'warehouse');
        }])->orderBy('name')->get();

        // Cari Akun Sales Internal untuk gudang terpilih
        $internalSales = User::where('warehouse_id', $warehouse->id)
            ->where(function($q) {
                $q->where('username', 'like', '%sales_%')
                  ->orWhere('name', 'like', '%internal%');
            })
            ->whereHas('roles', function($q) {
                $q->where('name', 'sales');
            })
            ->first();

        // List Sales lain (Hanya yang satu depo cok!)
        $allSales = User::where('warehouse_id', $warehouse->id)
            ->whereHas('roles', function($q) {
                $q->where('name', 'sales');
            })->orderBy('name')->get();

        return view('wh.direct_sales_index', [
            'me'               => $me,
            'warehouses'       => $warehouses,
            'warehouse'        => $warehouse,
            'selectedDate'     => $selectedDate,
            'products'         => $products,
            'internalSales'    => $internalSales,
            'allSales'         => $allSales,
        ]);
    }

    /**
     * Proses Simpan Penjualan Langsung
     */
    public function store(Request $request)
    {
        $me = auth()->user();
        $data = $request->validate([
            'buyer_type'    => 'required|in:sales,pareto,umum',
            'sales_id'      => 'nullable|exists:users,id',
            'customer_name' => 'nullable|string|max:255',
            'items'         => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.discount_mode' => 'nullable|string|in:unit,fixed',
            'items.*.discount_per_unit' => 'nullable|integer|min:0',
            'items.*.discount_fixed_amount' => 'nullable|integer|min:0',
            // Payment
            'cash_amount'     => 'nullable|integer|min:0',
            'transfer_amount' => 'nullable|integer|min:0',
            'transfer_proof'  => 'nullable|image|max:5120', // 5MB
            'handover_date'   => 'required|date',
            'warehouse_id'    => 'required|exists:warehouses,id',
        ]);

        try {
            DB::beginTransaction();

            $me = auth()->user();
            // Jika Admin/Superadmin, gunakan warehouse_id dari request. Jika tidak, paksa pake profil sendiri.
            $warehouseId = $request->warehouse_id;
            if (!$me->hasRole(['superadmin', 'admin'])) {
                $warehouseId = $me->warehouse_id;
            }

            if (!$warehouseId) {
                throw new \Exception("Warehouse context not found.");
            }
            
            $date = Carbon::parse($request->handover_date)->toDateString();

            // 1. Tentukan Sales ID (Owner Transaksi)
            $salesId = null;
            if ($data['buyer_type'] === 'sales') {
                $salesId = $data['sales_id'];
            } else {
                // Cari sales internal depo ini
                $internal = User::where('warehouse_id', $warehouseId)
                    ->where(function($q) {
                        $q->where('username', 'like', '%sales_%')
                          ->orWhere('name', 'like', '%internal%');
                    })->first();
                
                $salesId = $internal ? $internal->id : $data['sales_id'];
            }

            if (!$salesId) {
                throw new \Exception("Could not find a valid Sales account for this transaction.");
            }

            // 2. Generate Code SI (Sales Internal)
            $dayPrefix = Carbon::parse($date)->format('ymd');
            $codePrefix = 'SI-' . $dayPrefix . '-'; // Prefix SI sesuai request lo cok
            $lastToday = SalesHandover::where('code', 'like', $codePrefix . '%')->orderByDesc('id')->first();
            $nextNumber = $lastToday ? ((int) substr($lastToday->code, -4)) + 1 : 1;
            $code = $codePrefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // 3. Simpan Header (Langsung CLOSED)
            $handover = SalesHandover::create([
                'code'          => $code,
                'warehouse_id'  => $warehouseId,
                'sales_id'      => $salesId,
                'handover_date' => $date,
                'status'        => 'closed',
                'issued_by'     => $me->id,
                'closed_by'     => $me->id,
                'is_direct_sale' => true,
                'buyer_type'    => $data['buyer_type'],
                'customer_name' => $data['customer_name'],
                'cash_amount'     => $data['cash_amount'] ?? 0,
                'transfer_amount' => $data['transfer_amount'] ?? 0,
            ]);

            // Handle Proof Upload
            if ($request->hasFile('transfer_proof')) {
                $path = $request->file('transfer_proof')->store('transfer_proofs/direct', 'public');
                $handover->transfer_proof_path = $path;
                $handover->save();
            }

            $totalDispatched = 0;
            $totalSold = 0;
            $totalDiscount = 0;

            // 4. Simpan Items & Potong Stok GUDANG
            foreach ($data['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $qty = (int)$row['qty'];
                $unitPrice = (int)$product->selling_price;

                // Hitung Diskon
                $discountMode = $row['discount_mode'] ?? 'unit';
                $discountPerUnit = (int)($row['discount_per_unit'] ?? 0);
                $discountFixed = (int)($row['discount_fixed_amount'] ?? 0);

                if ($discountMode === 'fixed') {
                    $totalRowDiscount = $discountFixed;
                    $effectiveDiscountPerUnit = $qty > 0 ? (int)floor($discountFixed / $qty) : 0;
                } else {
                    $totalRowDiscount = $discountPerUnit * $qty;
                    $effectiveDiscountPerUnit = $discountPerUnit;
                }

                $lineTotalStart = $unitPrice * $qty;
                $lineTotalSold = max(0, $lineTotalStart - $totalRowDiscount);

                SalesHandoverItem::create([
                    'handover_id'  => $handover->id,
                    'product_id'   => $product->id,
                    'qty_start'    => $qty,
                    'qty_returned' => 0,
                    'qty_sold'     => $qty, // Langsung terjual semua
                    'unit_price'   => $unitPrice,
                    'discount_mode' => $discountMode,
                    'discount_per_unit' => $effectiveDiscountPerUnit,
                    'discount_fixed_amount' => $discountFixed,
                    'discount_total' => $totalRowDiscount,
                    'unit_price_after_discount' => max(0, $unitPrice - $effectiveDiscountPerUnit),
                    'line_total_after_discount' => $lineTotalSold,
                    'line_total_start' => $lineTotalStart,
                    'line_total_sold'  => $lineTotalSold,
                    'payment_status'   => 'approved', // Langsung approve
                ]);

                // 🔥 POTONG STOK GUDANG (Direct Movement)
                $stock = StockLevel::where('owner_id', $warehouseId)
                    ->where('product_id', $product->id)
                    ->where('owner_type', 'warehouse')
                    ->first();

                if (!$stock || $stock->quantity < $qty) {
                    throw new \Exception("Insufficient stock for {$product->name} in Warehouse.");
                }

                $oldQty = $stock->quantity;
                $stock->quantity -= $qty;
                $stock->save();

                // Log Movement (Sesuaikan dengan skema asli: from_type, to_type, etc)
                StockMovement::create([
                    'product_id'   => $product->id,
                    'from_type'    => 'warehouse',
                    'from_id'      => $warehouseId,
                    'to_type'      => null, 
                    'to_id'        => null,
                    'quantity'     => $qty,
                    'status'       => 'completed',
                    'approved_by'  => $me->id,
                    'approved_at'  => now(),
                    'note'         => "Direct Sale ({$data['buyer_type']}) to {$handover->customer_name}: {$code}",
                ]);

                $totalDispatched += $lineTotalStart;
                $totalSold += $lineTotalSold;
                $totalDiscount += $totalRowDiscount;
            }

            // Update Header Totals
            $handover->update([
                'total_dispatched_amount' => $totalSold, // Di POS, jumlah barang dibawa (Net) = jumlah terjual (Net)
                'total_sold_amount'       => $totalSold, 
                'discount_total'          => $totalDiscount,
                'grand_total'             => $totalSold,
            ]);

            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => "Direct sale {$code} processed successfully.",
                'redirect' => route('warehouse.direct_sales.index')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
