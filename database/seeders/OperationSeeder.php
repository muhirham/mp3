<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\StockRequest;
use App\Models\RequestRestock;
use App\Models\StockMovement;
use App\Models\SalesReport;
use App\Models\SalesReturn;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Supplier;
use Illuminate\Support\Facades\Hash;

class OperationSeeder extends Seeder
{
    public function run(): void
    {
        // ===== Ambil data referensi (assume sudah dibuat oleh CoreSeeder)
        // Pakai produk dari data MP3:
        // 0097-P = VO 5GB-3D (Voucher isi 5GB 3 Hari)
        // 0092-P = Modem Pool 16 Port MP3
        $prod1    = Product::firstWhere('product_code', '0097-P'); // voucher
        $prod2    = Product::firstWhere('product_code', '0092-P'); // aset modem pool

        $supplier = Supplier::first();

        // CS BUKITTINGGI sebagai warehouse utama
        $wh = Warehouse::firstWhere('warehouse_code', 'CS-BUKITTINGGI');

        // Admin via pivot roles (BUKAN kolom users.role)
        $admin = User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->first();

        // Fallback admin minimal kalau belum ada
        if (!$admin) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@local'],
                [
                    'name'     => 'Admin Pusat',
                    'username' => 'admin',
                    'phone'    => '081200000000',
                    'password' => Hash::make('password123'),
                    'status'   => 'active',
                ]
            );
            // helper dari model User (sudah kamu buat sebelumnya)
            $admin->assignRole('admin');
        }

        // User gudang & sales (dibuat CoreSeeder)
        $wh_bkt    = User::firstWhere('username', 'wh_bukittinggi');        // user warehouse CS Bukittinggi
        $sales_bkt = User::firstWhere('username', 'sales_bukittinggi');     // user sales CS Bukittinggi

        // Kalau belum lengkap, skip seeder ini (biar error-nya jelas di log / tinker)
        if (!$prod1 || !$prod2 || !$supplier || !$wh || !$wh_bkt || !$sales_bkt) {
            return;
        }

        // ===== Stock Request (Sales -> Warehouse)
        StockRequest::create([
            'requester_type'      => 'sales',
            'requester_id'        => $sales_bkt->id,
            'approver_type'       => 'warehouse',
            'approver_id'         => $wh_bkt->id,
            'product_id'          => $prod1->id,
            'quantity_requested'  => 10,
            'quantity_approved'   => 10,
            'status'              => 'approved',
            'note'                => 'Request stok voucher data VO 5GB-3D untuk CS Bukittinggi',
        ]);

        // ===== Request Restock (Warehouse -> Supplier via Admin)
        RequestRestock::create([
            'supplier_id'        => $supplier->id,
            'product_id'         => $prod2->id,
            'warehouse_id'       => $wh->id,              // gudang pengaju
            'requested_by'       => $wh_bkt->id,          // user yang mengajukan
            'quantity_requested' => 5,
            'quantity_received'  => 5,
            'cost_per_item'      => 4500000,
            'total_cost'         => 22500000,
            'status'             => 'received',
            'approved_by'        => $admin->id,
            'approved_at'        => now(),
            'received_at'        => now(),
            'note'               => 'Restock Modem Pool 16 Port MP3',
        ]);

        // ===== Stock Movement (Pusat -> Warehouse)
        StockMovement::create([
            'product_id'  => $prod1->id,
            'from_type'   => 'pusat',
            'to_type'     => 'warehouse',
            'to_id'       => $wh->id,
            'quantity'    => 50,
            'status'      => 'completed',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'note'        => 'Distribusi voucher VO 5GB-3D ke CS Bukittinggi',
        ]);

        // ===== Sales Report
        SalesReport::create([
            'sales_id'        => $sales_bkt->id,
            'warehouse_id'    => $wh->id,
            'date'            => Carbon::now()->toDateString(),
            'total_sold'      => 8,
            'total_revenue'   => 8 * 10900, // kira-kira sesuai harga voucher
            'stock_remaining' => 2,
            'damaged_goods'   => 0,
            'goods_returned'  => 0,
            'notes'           => 'Penjualan harian voucher oleh sales CS Bukittinggi',
            'status'          => 'approved',
            'approved_by'     => $wh_bkt->id,
            'approved_at'     => now(),
        ]);

        // ===== Sales Return
        SalesReturn::create([
            'sales_id'     => $sales_bkt->id,
            'warehouse_id' => $wh->id,
            'product_id'   => $prod1->id,
            'quantity'     => 1,
            'condition'    => 'damaged',
            'reason'       => 'Voucher rusak / tidak terbaca',
            'status'       => 'approved',
            'approved_by'  => $wh_bkt->id,
            'approved_at'  => now(),
        ]);
    }
}
