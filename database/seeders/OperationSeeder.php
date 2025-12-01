<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

use App\Models\Company;
use App\Models\StockRequest;
use App\Models\RequestRestock;
use App\Models\StockMovement;
use App\Models\SalesReport;
use App\Models\SalesReturn;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Supplier;

class OperationSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * ===========================
         *  1) COMPANY (MANDAU)
         * ===========================
         */
        $mandau = Company::updateOrCreate(
            ['code' => 'MANDAU'],
            [
                'name'            => 'Mandau',
                'legal_name'      => 'PT Mandiri Daya Utama Nusantara',
                'short_name'      => 'MANDAU',
                'address'         => 'Komplek Golden Plaza Blok C17, Jl. RS Fatmawati No. 15',
                'city'            => 'Jakarta Selatan',
                'province'        => 'DKI Jakarta',
                'phone'           => '+62 21 7590 9945',
                'email'           => 'info@mandau.id',
                'website'         => 'https://mandau.id',
                'tax_number'      => null,          // isi NPWP nanti kalau sudah ada
                'logo_path'       => null,          // nanti diisi path upload logo besar
                'logo_small_path' => null,          // nanti diisi path logo kecil (kop surat)
                'is_default'      => true,
                'is_active'       => true,
            ]
        );

        /*
         * ===========================
         *  2) DATA REFERENSI
         * ===========================
         * Asumsi CoreSeeder sudah jalan duluan.
         */

        // Produk:
        // 0097-P = VO 5GB-3D (Voucher isi 5GB 3 Hari)
        // 0092-P = Modem Pool 16 Port MP3
        $prod1 = Product::firstWhere('product_code', '0097-P'); // voucher
        $prod2 = Product::firstWhere('product_code', '0092-P'); // aset modem pool

        $supplier = Supplier::first();

        // Warehouse utama → pakai DEPO-BUKITTINGGI (sesuai CoreSeeder)
        $wh = Warehouse::where('warehouse_code', 'DEPO-BUKITTINGGI')->first()
            ?: Warehouse::first();

        // Admin via pivot roles
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->first();

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
            // helper dari model User (sudah kamu buat di model User)
            if (method_exists($admin, 'assignRole')) {
                $admin->assignRole('admin');
            }
        }

        // User gudang & sales (dibuat CoreSeeder)
        $wh_bkt    = User::firstWhere('username', 'wh_bukittinggi');
        $sales_bkt = User::firstWhere('username', 'sales_bukittinggi');

        // Kalau referensi belum lengkap, seeder operasi di-skip (biar ga error)
        if (!$prod1 || !$prod2 || !$supplier || !$wh || !$wh_bkt || !$sales_bkt) {
            return;
        }

        /*
         * ===========================
         *  3) STOCK REQUEST (Sales -> Warehouse)
         * ===========================
         */
        StockRequest::firstOrCreate(
            [
                'requester_type' => 'sales',
                'requester_id'   => $sales_bkt->id,
                'product_id'     => $prod1->id,
                'status'         => 'approved',
            ],
            [
                'approver_type'      => 'warehouse',
                'approver_id'        => $wh_bkt->id,
                'quantity_requested' => 10,
                'quantity_approved'  => 10,
                'note'               => 'Request stok voucher data VO 5GB-3D untuk DEPO Bukittinggi (dummy seeder).',
            ]
        );

        /*
         * ===========================
         *  4) REQUEST RESTOCK (Warehouse -> Supplier)
         * ===========================
         * Wajib isi kolom `code` (NOT NULL).
         */
        RequestRestock::updateOrCreate(
            ['code' => 'RR-SEED-0001'],
            [
                'supplier_id'        => $supplier->id,
                'product_id'         => $prod2->id,
                'warehouse_id'       => $wh->id,
                'requested_by'       => $wh_bkt->id,
                'quantity_requested' => 5,
                'quantity_received'  => 5,
                'cost_per_item'      => 4_500_000,
                'total_cost'         => 22_500_000,
                'status'             => 'received',
                'approved_by'        => $admin->id,
                'approved_at'        => now(),
                'received_at'        => now(),
                'note'               => 'Restock Modem Pool 16 Port MP3 (dummy data seeder).',
            ]
        );

        /*
         * ===========================
         *  5) STOCK MOVEMENT (Pusat -> Warehouse)
         * ===========================
         */
        StockMovement::firstOrCreate(
            [
                'product_id' => $prod1->id,
                'from_type'  => 'pusat',
                'to_type'    => 'warehouse',
                'to_id'      => $wh->id,
            ],
            [
                'quantity'    => 50,
                'status'      => 'completed',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'note'        => 'Distribusi awal voucher VO 5GB-3D ke DEPO Bukittinggi (dummy seeder).',
            ]
        );

        /*
         * ===========================
         *  6) SALES REPORT (harian)
         * ===========================
         */
        SalesReport::firstOrCreate(
            [
                'sales_id'     => $sales_bkt->id,
                'warehouse_id' => $wh->id,
                'date'         => Carbon::now()->toDateString(),
            ],
            [
                'total_sold'      => 8,
                'total_revenue'   => 8 * 10_900,
                'stock_remaining' => 2,
                'damaged_goods'   => 0,
                'goods_returned'  => 0,
                'notes'           => 'Penjualan harian voucher oleh sales DEPO Bukittinggi (dummy data seeder).',
                'status'          => 'approved',
                'approved_by'     => $wh_bkt->id,
                'approved_at'     => now(),
            ]
        );

        /*
         * ===========================
         *  7) SALES RETURN
         * ===========================
         */
        SalesReturn::firstOrCreate(
            [
                'sales_id'     => $sales_bkt->id,
                'warehouse_id' => $wh->id,
                'product_id'   => $prod1->id,
                'quantity'     => 1,
            ],
            [
                'condition'   => 'damaged',
                'reason'      => 'Voucher rusak / tidak terbaca (dummy seeder).',
                'status'      => 'approved',
                'approved_by' => $wh_bkt->id,
                'approved_at' => now(),
            ]
        );
    }
}
