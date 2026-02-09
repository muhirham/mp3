<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
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
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Models\Role;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use App\Models\WarehouseTransferLog;


class OperationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            /*
            |----------------------------------------------------------------------
            | 0) ADMIN FALLBACK + USER REF (biar relasi seed aman)
            |----------------------------------------------------------------------
            */
            $admin = User::whereHas('roles', function ($q) {
                    $q->whereIn('slug', ['superadmin', 'admin']);
                })->first();

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

            $whUser    = User::firstWhere('username', 'wh_bukittinggi')
                        ?: User::whereHas('roles', fn($q) => $q->where('slug','warehouse'))->first();

            $salesUser = User::firstWhere('username', 'sales_bukittinggi')
                        ?: User::whereHas('roles', fn($q) => $q->where('slug','sales'))->first();

            $procUser  = User::firstWhere('username', 'procurement')
                        ?: User::whereHas('roles', fn($q) => $q->where('slug','procurement'))->first();

            $ceoUser   = User::firstWhere('username', 'ceo')
                        ?: User::whereHas('roles', fn($q) => $q->where('slug','ceo'))->first();

            /*
            |----------------------------------------------------------------------
            | 1) COMPANY (REAL)
            |----------------------------------------------------------------------
            */
            $company = Company::updateOrCreate(
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

            /*
            |----------------------------------------------------------------------
            | 2) REFERENCE DATA (pake data real dari CoreSeeder lu)
            |----------------------------------------------------------------------
            */
            $supplier = Supplier::where('supplier_code', 'SUP-0001')->first() ?: Supplier::first();

            $wh = Warehouse::where('warehouse_code', 'DEPO-BUKITTINGGI')->first() ?: Warehouse::first();

            $prodVoucher = Product::firstWhere('product_code', '0097-P') ?: Product::first();
            $prodModem   = Product::firstWhere('product_code', '0092-P')
                        ?: Product::orderByDesc('purchasing_price')->first()
                        ?: Product::first();

            /*
            |----------------------------------------------------------------------
            | 3) ACTIVITY LOG + STOCK SNAPSHOT (MINIMAL TAPI ADA)
            |----------------------------------------------------------------------
            */
            if ($admin && $this->tableExists(ActivityLog::class)) {
                $t = $this->tableName(ActivityLog::class);

                $this->upsert($t,
                    ['user_id' => $admin->id, 'action' => 'Seeder Init', 'entity_type' => 'System'],
                    [
                        'entity_id'   => null,
                        'description' => 'Seeder initial setup (real testing data) berhasil dibuat.',
                    ]
                );
            }

            if ($prodVoucher && $this->tableExists(StockSnapshot::class)) {
                $t = $this->tableName(StockSnapshot::class);

                $this->upsert($t,
                    [
                        'owner_type'  => 'pusat',
                        'owner_id'    => 0,
                        'product_id'  => $prodVoucher->id,
                        'recorded_at' => Carbon::now()->toDateString(),
                    ],
                    [
                        'quantity' => 100,
                    ]
                );

                if ($wh) {
                    $this->upsert($t,
                        [
                            'owner_type'  => 'warehouse',
                            'owner_id'    => $wh->id,
                            'product_id'  => $prodVoucher->id,
                            'recorded_at' => Carbon::now()->toDateString(),
                        ],
                        ['quantity' => 50]
                    );
                }

                if ($salesUser) {
                    $this->upsert($t,
                        [
                            'owner_type'  => 'sales',
                            'owner_id'    => $salesUser->id,
                            'product_id'  => $prodVoucher->id,
                            'recorded_at' => Carbon::now()->toDateString(),
                        ],
                        ['quantity' => 20]
                    );
                }
            }

            /*
            |----------------------------------------------------------------------
            | 4) STOCK REQUEST (Sales -> Warehouse)
            |----------------------------------------------------------------------
            */
            if ($prodVoucher && $salesUser && $whUser && $this->tableExists(StockRequest::class)) {
                $t = $this->tableName(StockRequest::class);

                $this->upsert($t,
                    [
                        'requester_type' => 'sales',
                        'requester_id'   => $salesUser->id,
                        'product_id'     => $prodVoucher->id,
                    ],
                    [
                        'status'             => 'approved',
                        'approver_type'      => 'warehouse',
                        'approver_id'        => $whUser->id,
                        'quantity_requested' => 20,
                        'quantity_approved'  => 20,
                        'note'               => 'Permintaan stok voucher untuk operasional penjualan harian.',
                        'approved_at'        => now(),
                    ]
                );
            }

            /*
            |----------------------------------------------------------------------
            | 5) REQUEST RESTOCK (Warehouse -> Supplier)
            |----------------------------------------------------------------------
            */
            if ($supplier && $wh && $whUser && $prodModem && $this->tableExists(RequestRestock::class)) {
                $t = $this->tableName(RequestRestock::class);

                $code = 'RR-IOH-' . Carbon::now()->format('Ym') . '-0001';

                $this->upsert($t,
                    ['code' => $code],
                    [
                        'supplier_id'        => $supplier->id,
                        'product_id'         => $prodModem->id,
                        'warehouse_id'       => $wh->id,
                        'requested_by'       => $whUser->id,
                        'quantity_requested' => 2,
                        'quantity_received'  => 2,
                        'cost_per_item'      => 4500000,
                        'total_cost'         => 9000000,
                        'status'             => 'received',
                        'approved_by'        => $admin?->id,
                        'approved_at'        => now(),
                        'received_at'        => now(),
                        'note'               => 'Restock perangkat untuk kebutuhan depo (real testing).',
                    ]
                );
            }

            /*
            |----------------------------------------------------------------------
            | 6) STOCK MOVEMENT (Pusat -> Warehouse) & (Warehouse -> Sales)
            |----------------------------------------------------------------------
            */
            if ($prodVoucher && $wh && $this->tableExists(StockMovement::class)) {
                $t = $this->tableName(StockMovement::class);

                // Pusat -> Warehouse
                $this->insertIfNotExists($t,
                    [
                        'product_id' => $prodVoucher->id,
                        'from_type'  => 'pusat',
                        'to_type'    => 'warehouse',
                        'to_id'      => $wh->id,
                    ],
                    [
                        'from_id'     => 0,
                        'quantity'    => 200,
                        'status'      => 'completed',
                        'approved_by' => $admin?->id,
                        'approved_at' => now(),
                        'note'        => 'Distribusi stok awal voucher ke depo (real testing).',
                    ]
                );

                // Warehouse -> Sales (handover pagi)
                if ($salesUser) {
                    $this->insertIfNotExists($t,
                        [
                            'product_id' => $prodVoucher->id,
                            'from_type'  => 'warehouse',
                            'to_type'    => 'sales',
                            'from_id'    => $wh->id,
                            'to_id'      => $salesUser->id,
                        ],
                        [
                            'quantity'    => 50,
                            'status'      => 'completed',
                            'approved_by' => $whUser?->id,
                            'approved_at' => now(),
                            'note'        => 'Handover pagi: stok dibawa sales untuk jualan.',
                        ]
                    );
                }
            }

            /*
            |----------------------------------------------------------------------
            | 7) SALES REPORT + SALES RETURN (harian)
            |----------------------------------------------------------------------
            */
            if ($salesUser && $wh && $prodVoucher && $this->tableExists(SalesReport::class)) {
                $t = $this->tableName(SalesReport::class);

                $date = Carbon::now()->toDateString();
                $qtySold = 18;
                $unitPrice = 10900;
                $totalRevenue = $qtySold * $unitPrice;

                $this->upsert($t,
                    [
                        'sales_id'     => $salesUser->id,
                        'warehouse_id' => $wh->id,
                        'date'         => $date,
                    ],
                    [
                        'total_sold'      => $qtySold,
                        'total_revenue'   => $totalRevenue,
                        'stock_remaining' => 5,
                        'damaged_goods'   => 1,
                        'goods_returned'  => 1,
                        'notes'           => 'Laporan penjualan harian depo (real testing).',
                        'status'          => 'approved',
                        'approved_by'     => $whUser?->id,
                        'approved_at'     => now(),
                    ]
                );
            }

            if ($salesUser && $wh && $prodVoucher && $this->tableExists(SalesReturn::class)) {
                $t = $this->tableName(SalesReturn::class);

                $this->upsert($t,
                    [
                        'sales_id'     => $salesUser->id,
                        'warehouse_id' => $wh->id,
                        'product_id'   => $prodVoucher->id,
                        'quantity'     => 1,
                    ],
                    [
                        'condition'   => 'damaged',
                        'reason'      => 'Produk rusak / tidak layak jual (real testing).',
                        'status'      => 'approved',
                        'approved_by' => $whUser?->id,
                        'approved_at' => now(),
                    ]
                );
            }

            /*
            |----------------------------------------------------------------------
            | 8) PURCHASE ORDER + ITEMS (buat test approval procurement/CEO)
            | - 1 PO kecil (approved)
            | - 1 PO besar (pending) biar bisa ngetes popup approval
            |----------------------------------------------------------------------
            */
            if ($supplier && $wh && $prodModem && $prodVoucher && $this->tableExists(PurchaseOrder::class)) {

                // PO 1 (kecil)
                $this->seedPO(
                    code: 'PO-IOH-' . Carbon::now()->format('Ymd') . '-0001',
                    supplierId: $supplier->id,
                    orderedBy: $procUser?->id ?? $admin->id,
                    status: 'ordered',
                    approvalStatus: 'approved',
                    notes: 'PO operasional rutin (real testing).',
                    items: [
                        ['product_id' => $prodVoucher->id, 'warehouse_id' => $wh->id, 'qty' => 100, 'price' => 10900],
                    ]
                );

                // PO 2 (besar, buat test approval CEO)
                $this->seedPO(
                    code: 'PO-IOH-' . Carbon::now()->format('Ymd') . '-0002',
                    supplierId: $supplier->id,
                    orderedBy: $procUser?->id ?? $admin->id,
                    status: 'ordered',
                    approvalStatus: 'pending', // kalau enum DB lu nggak nerima, auto fallback (lihat fungsi seedPO)
                    notes: 'PO nilai besar untuk approval lanjutan (real testing).',
                    items: [
                        ['product_id' => $prodModem->id, 'warehouse_id' => $wh->id, 'qty' => 1, 'price' => 4500000],
                        ['product_id' => $prodVoucher->id, 'warehouse_id' => $wh->id, 'qty' => 200, 'price' => 10900],
                    ],
                    ceoUserId: $ceoUser?->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 9) WAREHOUSE TRANSFER (Warehouse -> Warehouse)
            |--------------------------------------------------------------------------
            */
            if (
                $prodVoucher &&
                $admin &&
                $this->tableExists(\App\Models\WarehouseTransfer::class)
            ) {
                $transferTable = $this->tableName(\App\Models\WarehouseTransfer::class);
                $itemTable     = $this->tableName(\App\Models\WarehouseTransferItem::class);
                $logTable      = $this->tableName(\App\Models\WarehouseTransferLog::class);

                // ambil 2 gudang berbeda
                $sourceWh = Warehouse::orderBy('id')->first();
                $destWh   = Warehouse::orderByDesc('id')->first();

                if ($sourceWh && $destWh && $sourceWh->id !== $destWh->id) {

                    $transferCode = 'TRF-WH-' . Carbon::now()->format('Ym') . '-0001';

                    // ambil HPP / harga beli
                    $unitCost = $prodVoucher->purchasing_price
                        ?? $prodVoucher->purchase_price
                        ?? 10000;

                    $qty      = 10;
                    $subtotal = $qty * $unitCost;

                    /** ======================
                     *  CREATE TRANSFER
                     *  ======================
                     */
                    $transfer = \App\Models\WarehouseTransfer::updateOrCreate(
                        ['transfer_code' => $transferCode],
                        $this->onlyExistingCols($transferTable, [
                            'source_warehouse_id'      => $sourceWh->id,
                            'destination_warehouse_id' => $destWh->id,
                            'status'                   => 'approved',
                            'total_cost'               => $subtotal,
                            'created_by'               => $admin->id,
                            'approved_source_by'       => $admin->id,
                            'approved_destination_by'  => $admin->id,
                            'approved_source_at'       => now(),
                            'approved_destination_at'  => now(),
                            'note'                     => 'Seed transfer antar gudang (real testing).',
                        ])
                    );

                    if ($transfer) {

                        /** ======================
                         *  ITEMS
                         *  ======================
                         */
                        if (Schema::hasTable($itemTable)) {
                            DB::table($itemTable)
                                ->where('warehouse_transfer_id', $transfer->id)
                                ->delete();

                            DB::table($itemTable)->insert(
                                $this->withTimestamps($itemTable, [
                                    'warehouse_transfer_id' => $transfer->id,
                                    'product_id'            => $prodVoucher->id,
                                    'qty_transfer'          => $qty,
                                    'qty_good'              => $qty,
                                    'qty_damaged'           => 0,
                                    'unit_cost'             => $unitCost,
                                    'subtotal_cost'         => $subtotal,
                                    'note'                  => 'Seeder GR: barang kondisi baik',
                                ])
                            );
                        }

                        /** ======================
                         *  LOGS
                         *  ======================
                         */
                        if (Schema::hasTable($logTable)) {
                            $logs = [
                                ['action' => 'CREATED'],
                                ['action' => 'SUBMITTED'],
                                ['action' => 'DEST_APPROVED'],
                                ['action' => 'GR_SOURCE'],
                            ];

                            foreach ($logs as $lg) {
                                DB::table($logTable)->insert(
                                    $this->withTimestamps($logTable, [
                                        'warehouse_transfer_id' => $transfer->id,
                                        'action'                => $lg['action'],
                                        'performed_by'          => $admin->id,
                                        'note'                  => 'Seeder auto log.',
                                    ])
                                );
                            }
                        }
                    }
                }
            }

        });
    }

    /* ============================================================
     * HELPERS: dynamic columns (biar selalu sesuai migration lu)
     * ============================================================ */

    private function tableName(string $modelClass): string
    {
        return (new $modelClass)->getTable();
    }

    private function tableExists(string $modelClass): bool
    {
        $table = $this->tableName($modelClass);
        return Schema::hasTable($table);
    }

    private function onlyExistingCols(string $table, array $data): array
    {
        if (!Schema::hasTable($table)) return [];
        $cols = Schema::getColumnListing($table);
        return array_intersect_key($data, array_flip($cols));
    }

    private function withTimestamps(string $table, array $data): array
    {
        if (!Schema::hasTable($table)) return $data;
        $cols = Schema::getColumnListing($table);

        if (in_array('created_at', $cols) && !isset($data['created_at'])) $data['created_at'] = now();
        if (in_array('updated_at', $cols) && !isset($data['updated_at'])) $data['updated_at'] = now();

        return $data;
    }

    private function upsert(string $table, array $unique, array $data): void
    {
        $uniqueFiltered = $this->onlyExistingCols($table, $unique);
        $dataFiltered   = $this->onlyExistingCols($table, $data);

        $payload = $this->withTimestamps($table, array_merge($uniqueFiltered, $dataFiltered));
        $uniqueFinal = array_intersect_key($payload, $uniqueFiltered);

        $updateData = array_diff_key($payload, $uniqueFinal);

        if (!empty($uniqueFinal)) {
            DB::table($table)->updateOrInsert($uniqueFinal, $updateData);
        }
    }

    private function insertIfNotExists(string $table, array $unique, array $data): void
    {
        $uniqueFiltered = $this->onlyExistingCols($table, $unique);
        $dataFiltered   = $this->onlyExistingCols($table, $data);

        $payload = $this->withTimestamps($table, array_merge($uniqueFiltered, $dataFiltered));

        if (empty($uniqueFiltered)) {
            DB::table($table)->insert($payload);
            return;
        }

        $exists = DB::table($table)->where($uniqueFiltered)->exists();
        if (! $exists) {
            DB::table($table)->insert($payload);
        }
    }

    private function seedPO(
        string $code,
        int $supplierId,
        int $orderedBy,
        string $status,
        string $approvalStatus,
        string $notes,
        array $items,
        ?int $ceoUserId = null
    ): void
    {
        $poTable = $this->tableName(PurchaseOrder::class);
        if (!Schema::hasTable($poTable)) return;

        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += ($it['qty'] * $it['price']);
        }

        // payload PO (disaring sesuai kolom yang ada)
        $poBase = [
            'supplier_id'     => $supplierId,
            'ordered_by'      => $orderedBy,
            'status'          => $status,
            'approval_status' => $approvalStatus,
            'subtotal'        => $subtotal,
            'discount_total'  => 0,
            'grand_total'     => $subtotal,
            'notes'           => $notes,
            'ordered_at'      => now(),
        ];

        // insert/update PO (pakai Eloquent biar gampang ambil id)
        try {
            $po = PurchaseOrder::updateOrCreate(
                ['po_code' => $code],
                $this->onlyExistingCols($poTable, $poBase)
            );
        } catch (QueryException $e) {
            // fallback kalau approval_status enum lu nggak nerima "pending"
            $fallback = $poBase;
            $fallback['approval_status'] = 'approved';

            $po = PurchaseOrder::updateOrCreate(
                ['po_code' => $code],
                $this->onlyExistingCols($poTable, $fallback)
            );
        }

        if (!$po) return;

        // bersihin items lama biar seed ulang rapi
        $itemTable = $this->tableName(PurchaseOrderItem::class);
        if (Schema::hasTable($itemTable)) {
            DB::table($itemTable)->where('purchase_order_id', $po->id)->delete();
        }

        foreach ($items as $it) {
            if (!Schema::hasTable($itemTable)) continue;

            $row = [
                'purchase_order_id' => $po->id,
                'product_id'        => $it['product_id'],
                'warehouse_id'      => $it['warehouse_id'],
                'qty_ordered'       => $it['qty'],
                'qty_received'      => 0,
                'unit_price'        => $it['price'],
                'line_total'        => ($it['qty'] * $it['price']),
            ];

            $row = $this->withTimestamps($itemTable, $this->onlyExistingCols($itemTable, $row));
            DB::table($itemTable)->insert($row);
        }

        // optional: kalau tabel PO lu punya approved_by/approved_at buat PO approved
        if (isset($po->approval_status) && $po->approval_status === 'approved') {
            $update = $this->onlyExistingCols($poTable, [
                'approved_by' => $orderedBy,
                'approved_at' => now(),
            ]);

            if (!empty($update)) {
                DB::table($poTable)->where('id', $po->id)->update($update);
            }
        }
    }
}

