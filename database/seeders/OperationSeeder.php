<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\ActivityLog;
use App\Models\StockSnapshot;
use App\Models\Company;
use App\Models\StockRequest;
use App\Models\RequestRestock;
use App\Models\StockMovement;
use App\Models\SalesReport;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Category;
use App\Models\Package;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Models\Role;

class OperationSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |----------------------------------------------------------------------
        | 0. ADMIN FALLBACK (dipakai beberapa bagian)
        |----------------------------------------------------------------------
        */
        $admin = User::whereHas('roles', function ($q) {
                $q->whereIn('slug', ['superadmin', 'admin']);
            })
            ->first();

        if (! $admin) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@local'],
                [
                    'name'     => 'Admin Pusat',
                    'username' => 'admin',
                    'phone'    => '081200000000',
                    'password' => bcrypt('password123'),
                    'status'   => 'active',
                ]
            );

            $role = Role::whereIn('slug', ['superadmin', 'admin'])
                ->orderByRaw("FIELD(slug, 'superadmin', 'admin')")
                ->first();

            if ($role && ! $admin->roles()->where('roles.id', $role->id)->exists()) {
                $admin->roles()->attach($role->id);
            }
        }

        /*
        |----------------------------------------------------------------------
        | 1. ACTIVITY LOG + STOCK SNAPSHOT (isi sama kayak ActivitySeeder)
        |----------------------------------------------------------------------
        */
        $productForActivity = Product::firstWhere('product_code', '0097-P') ?? Product::first();

        ActivityLog::firstOrCreate(
            [
                'user_id'     => $admin->id,
                'action'      => 'Seeder Init',
                'entity_type' => 'System',
            ],
            [
                'entity_id'   => null,
                'description' => 'Seeder initial setup data dummy MP3 berhasil dibuat.',
            ]
        );

        if ($productForActivity) {
            ActivityLog::firstOrCreate(
                [
                    'user_id'     => $admin->id,
                    'action'      => 'Stock Update',
                    'entity_type' => 'Product',
                    'entity_id'   => $productForActivity->id,
                ],
                [
                    'description' => 'Perubahan stok produk ' . $productForActivity->product_code .
                        ' (' . $productForActivity->name . ') oleh admin pusat.',
                ]
            );

            StockSnapshot::firstOrCreate(
                [
                    'owner_type'  => 'pusat',
                    'owner_id'    => 0,
                    'product_id'  => $productForActivity->id,
                    'recorded_at' => now()->toDateString(),
                ],
                ['quantity' => 100]
            );
        }

        /*
        |----------------------------------------------------------------------
        | 2. COMPANY MANDAU + OPERASIONAL (isi sama kayak OperationSeeder lu)
        |----------------------------------------------------------------------
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
                'tax_number'      => null,
                'logo_path'       => null,
                'logo_small_path' => null,
                'is_default'      => true,
                'is_active'       => true,
            ]
        );

        // Produk referensi
        $prod1 = Product::firstWhere('product_code', '0097-P'); // VO 5GB-3D
        $prod2 = Product::firstWhere('product_code', '0092-P'); // Modem Pool 16 Port

        $supplier = Supplier::first();

        // Warehouse utama → DEPO-BUKITTINGGI (kalau nggak ada, pakai warehouse pertama)
        $wh = Warehouse::where('warehouse_code', 'DEPO-BUKITTINGGI')->first()
            ?: Warehouse::first();

        $wh_bkt    = User::firstWhere('username', 'wh_bukittinggi');
        $sales_bkt = User::firstWhere('username', 'sales_bukittinggi');

        if ($prod1 && $prod2 && $supplier && $wh && $wh_bkt && $sales_bkt) {

            // 2.1 STOCK REQUEST (Sales -> Warehouse)
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

            // 2.2 REQUEST RESTOCK (Warehouse -> Supplier)
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

            // 2.3 STOCK MOVEMENT (Pusat -> Warehouse)
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

            // 2.4 SALES REPORT (Harian)
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

            // 2.5 SALES RETURN
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

        /*
        |----------------------------------------------------------------------
        | 3. DUMMY PO (gabungan PoDummySeeder, tapi TANPA kolom sku)
        |   - kalau produk demo belum ada → dibuat 3
        |   - kalau sudah ada product_code sama → di-update aja
        |----------------------------------------------------------------------
        */
        DB::transaction(function () use ($admin) {

            // 3.1 SUPPLIER DEMO
            $suppliersData = [
                [
                    'code'    => 'SUP-DEMO-01',
                    'name'    => 'PT Demo Supplier Satu',
                    'address' => 'Jl. Demo No. 1, Jakarta',
                    'phone'   => '0811-1111-111',
                ],
                [
                    'code'    => 'SUP-DEMO-02',
                    'name'    => 'PT Demo Supplier Dua',
                    'address' => 'Jl. Demo No. 2, Jakarta',
                    'phone'   => '0811-2222-222',
                ],
                [
                    'code'    => 'SUP-DEMO-03',
                    'name'    => 'PT Demo Supplier Tiga',
                    'address' => 'Jl. Demo No. 3, Jakarta',
                    'phone'   => '0811-3333-333',
                ],
            ];

            $suppliers = [];
            foreach ($suppliersData as $row) {
                $suppliers[$row['code']] = Supplier::updateOrCreate(
                    ['supplier_code' => $row['code']],
                    [
                        'name'    => $row['name'],
                        'address' => $row['address'],
                        'phone'   => $row['phone'],
                        'note'    => 'Dummy supplier untuk testing PO',
                    ]
                );
            }

            // 3.2 WAREHOUSE DEMO (tanpa kolom city)
            $warehousesData = [
                [
                    'code'    => 'WH-DEMO-01',
                    'name'    => 'Warehouse Pusat',
                    'address' => 'Jakarta Pusat',
                ],
                [
                    'code'    => 'WH-DEMO-02',
                    'name'    => 'Warehouse Barat',
                    'address' => 'Jakarta Barat',
                ],
                [
                    'code'    => 'WH-DEMO-03',
                    'name'    => 'Warehouse Timur',
                    'address' => 'Jakarta Timur',
                ],
            ];

            $warehouses = [];
            foreach ($warehousesData as $row) {
                $warehouses[$row['code']] = Warehouse::updateOrCreate(
                    ['warehouse_code' => $row['code']],
                    [
                        'warehouse_name' => $row['name'],
                        'address'        => $row['address'],
                        'note'           => 'Warehouse demo untuk dummy PO',
                    ]
                );
            }

            // 3.3 CATEGORY & PACKAGE DEMO
            $category = Category::updateOrCreate(
                ['category_code' => 'CAT-DEMO'],
                [
                    'category_name' => 'Kategori Demo',
                    'description'   => 'Kategori contoh untuk dummy PO',
                ]
            );

            // table packages lu cuma punya package_name
            $package = Package::updateOrCreate(
                ['package_name' => 'Unit'],
                ['package_name' => 'Unit']
            );

            // 3.4 PRODUCT DEMO (PAKAI product_code, BUKAN sku)
            $productsData = [
                [
                    'code'          => 'PRD-DEMO-001',
                    'name'          => 'Modem Fiber 1 Port',
                    'supplier_code' => 'SUP-DEMO-01',
                    'base_price'    => 200_000,
                ],
                [
                    'code'          => 'PRD-DEMO-002',
                    'name'          => 'Router Wifi AC1200',
                    'supplier_code' => 'SUP-DEMO-02',
                    'base_price'    => 500_000,
                ],
                [
                    'code'          => 'PRD-DEMO-003',
                    'name'          => 'Kabel UTP Cat6 305m',
                    'supplier_code' => 'SUP-DEMO-03',
                    'base_price'    => 900_000,
                ],
            ];

            $products = [];
            foreach ($productsData as $row) {
                $supplier = $suppliers[$row['supplier_code']];

                $products[$row['code']] = Product::updateOrCreate(
                    ['product_code' => $row['code']],
                    [
                        'product_code'     => $row['code'],
                        'name'             => $row['name'],
                        'category_id'      => $category->id,
                        'package_id'       => $package->id,
                        'supplier_id'      => $supplier->id,
                        'description'      => 'Produk dummy untuk testing PO & PDF',
                        'stock_minimum'    => 1,
                        'purchasing_price' => $row['base_price'],
                        'selling_price'    => $row['base_price'] * 1.2,
                    ]
                );
            }

            // 3.5 USER PEMBUAT PO (superadmin kalau ada)
            $creator = User::whereHas('roles', function ($q) {
                    $q->where('slug', 'superadmin');
                })
                ->first() ?? $admin;

            // 3.6 PURCHASE ORDER DEMO
            $poDefs = [
                [
                    'code'      => 'PO-DEMO-LOW',
                    'supplier'  => 'SUP-DEMO-01',
                    'warehouse' => 'WH-DEMO-01',
                    'note'      => 'Contoh PO di bawah 1 juta (hanya Procurement).',
                    'items'     => [
                        [
                            'product_code' => 'PRD-DEMO-001',
                            'qty'          => 3,
                            'price'        => 200_000,
                        ], // 600k
                    ],
                ],
                [
                    'code'      => 'PO-DEMO-MID',
                    'supplier'  => 'SUP-DEMO-02',
                    'warehouse' => 'WH-DEMO-02',
                    'note'      => 'Contoh PO antara 1–2 juta.',
                    'items'     => [
                        [
                            'product_code' => 'PRD-DEMO-002',
                            'qty'          => 3,
                            'price'        => 500_000,
                        ], // 1.5M
                    ],
                ],
                [
                    'code'      => 'PO-DEMO-HIGH',
                    'supplier'  => 'SUP-DEMO-03',
                    'warehouse' => 'WH-DEMO-03',
                    'note'      => 'Contoh PO di atas 2 juta (harus naik ke CEO).',
                    'items'     => [
                        [
                            'product_code' => 'PRD-DEMO-003',
                            'qty'          => 3,
                            'price'        => 900_000,
                        ], // 2.7M
                    ],
                ],
            ];

            foreach ($poDefs as $def) {
                $supplier  = $suppliers[$def['supplier']];
                $warehouse = $warehouses[$def['warehouse']];

                $subtotal = 0;
                foreach ($def['items'] as $it) {
                    $subtotal += $it['qty'] * $it['price'];
                }

                $po = PurchaseOrder::updateOrCreate(
                    ['po_code' => $def['code']],
                    [
                        'supplier_id'     => $supplier->id,
                        'ordered_by'      => $creator?->id,
                        'status'          => 'ordered',
                        'approval_status' => 'approved',
                        'subtotal'        => $subtotal,
                        'discount_total'  => 0,
                        'grand_total'     => $subtotal,
                        'notes'           => $def['note'],
                        'ordered_at'      => now(),
                    ]
                );

                // bersihin item lama biar seeding ulang ga dobel
                $po->items()->delete();

                foreach ($def['items'] as $it) {
                    $product = $products[$it['product_code']];

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'product_id'        => $product->id,
                        'warehouse_id'      => $warehouse->id,
                        'qty_ordered'       => $it['qty'],
                        'qty_received'      => 0,
                        'unit_price'        => $it['price'],
                        'line_total'        => $it['qty'] * $it['price'],
                    ]);
                }
            }
        });
    }
}
