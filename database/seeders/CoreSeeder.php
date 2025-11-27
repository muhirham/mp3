<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\StockLevel;
use App\Models\Role;
use App\Models\Package; // <— PENTING: cuma Package, bukan Unit

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * ===========================
         *  SUPPLIERS
         * ===========================
         */

        $supElang = Supplier::updateOrCreate(
            ['supplier_code' => 'SUP-ELANG'],
            [
                'name'          => 'PT Elang Digital Indonesia',
                'address'       => 'Jl. Contoh No. 1, Jakarta',
                'phone'         => '081234567890',
                'note'          => 'Supplier utama produk MP3 (voucher & aset)',
                'bank_name'     => 'Bank MP3',
                'bank_account'  => '1234567890',
            ]
        );

        $supBinaMandiri = Supplier::updateOrCreate(
            ['supplier_code' => 'SUP-BINA-MANDIRI'],
            [
                'name'          => 'Pt Bina Mandiri',
                'address'       => 'Jl. Bina Mandiri',
                'phone'         => '081200000100',
                'note'          => 'Supplier voucher & perangkat',
                'bank_name'     => 'Bank Mandiri',
                'bank_account'  => '9876543210',
            ]
        );

        $supBinaAngkasa = Supplier::updateOrCreate(
            ['supplier_code' => 'SUP-BINA-ANGKASA'],
            [
                'name'          => 'Pt Bina angkasa',
                'address'       => 'Jl. Bina Angkasa',
                'phone'         => '081200000101',
                'note'          => 'Supplier voucher & aset',
                'bank_name'     => 'Bank Angkasa',
                'bank_account'  => '5678901234',
            ]
        );

        /*
         * ===========================
         *  PACKAGES (UOM)
         * ===========================
         */

        $pkgBox = Package::updateOrCreate(
            ['package_name' => 'BOX'],
            ['package_name' => 'BOX']
        );

        $pkgPcs = Package::updateOrCreate(
            ['package_name' => 'PCS'],
            ['package_name' => 'PCS']
        );

        $pkgRp = Package::updateOrCreate(
            ['package_name' => 'Rp.'],
            ['package_name' => 'Rp.']
        );

        /*
         * ===========================
         *  WAREHOUSE / CABANG DEPO
         * ===========================
         */

        $wh1 = Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-BUKITTINGGI'],
            [
                'warehouse_name' => 'DEPO BUKITTINGGI',
                'address'        => 'DEPO BUKITTINGGI',
                'note'           => 'Cabang DEPO Bukittinggi',
            ]
        );

        $wh2 = Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-PADANG'],
            [
                'warehouse_name' => 'DEPO PADANG',
                'address'        => 'DEPO PADANG',
                'note'           => 'Cabang DEPO Padang',
            ]
        );

        Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-DHARMASRAYA'],
            [
                'warehouse_name' => 'DEPO DHARMASRAYA',
                'address'        => 'DEPO DHARMASRAYA',
                'note'           => 'Cabang DEPO Dharmasraya',
            ]
        );

        Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-PARIAMAN'],
            [
                'warehouse_name' => 'DEPO PARIAMAN',
                'address'        => 'DEPO PARIAMAN',
                'note'           => 'Cabang DEPO Pariaman',
            ]
        );

        Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-PASAMAN-BARAT'],
            [
                'warehouse_name' => 'DEPO PASAMAN BARAT',
                'address'        => 'DEPO PASAMAN BARAT',
                'note'           => 'Cabang DEPO Pasaman Barat',
            ]
        );

        Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-PAYAKUMBUH'],
            [
                'warehouse_name' => 'DEPO PAYAKUMBUH',
                'address'        => 'DEPO PAYAKUMBUH',
                'note'           => 'Cabang DEPO Payakumbuh',
            ]
        );

        Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-PESISIR-SELATAN'],
            [
                'warehouse_name' => 'DEPO PESISIR SELATAN',
                'address'        => 'DEPO PESISIR SELATAN',
                'note'           => 'Cabang DEPO Pesisir Selatan',
            ]
        );

        Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-SOLOK'],
            [
                'warehouse_name' => 'DEPO SOLOK',
                'address'        => 'DEPO SOLOK',
                'note'           => 'Cabang DEPO Solok',
            ]
        );

        Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-TANAH-DATAR'],
            [
                'warehouse_name' => 'DEPO TANAH DATAR',
                'address'        => 'DEPO TANAH DATAR',
                'note'           => 'Cabang DEPO Tanah Datar',
            ]
        );

        /*
         * ===========================
         *  CATEGORIES
         * ===========================
         */

        $catVoucher = Category::updateOrCreate(
            ['category_code' => 'CAT-VCR'],
            [
                'category_name' => 'Voucher',
                'description'   => 'Produk voucher data / fisik',
            ]
        );

        $catSaldo = Category::updateOrCreate(
            ['category_code' => 'CAT-SLD'],
            [
                'category_name' => 'Saldo Elektronik',
                'description'   => 'Produk saldo elektronik',
            ]
        );

        $catAset = Category::updateOrCreate(
            ['category_code' => 'CAT-AST'],
            [
                'category_name' => 'Aset & Perangkat',
                'description'   => 'Modem, brankas, filling cabinet, sewa depo, dll.',
            ]
        );

        /*
         * ===========================
         *  PRODUCTS (sesuai tabel)
         * ===========================
         */

        $p_blank = Product::updateOrCreate(
            ['product_code' => '0079-P'],
            [
                'name'             => 'VO-BLANK-MINI3-CSS',
                'category_id'      => $catVoucher->id,
                'package_id'       => $pkgBox->id,
                'supplier_id'      => $supBinaMandiri->id,
                'description'      => 'Voucher Fisik Kosong',
                'stock_minimum'    => 10,
                'purchasing_price' => 500,
                'selling_price'    => 500,
            ]
        );

        $p_vo_elc = Product::updateOrCreate(
            ['product_code' => '0081-P'],
            [
                'name'             => 'VO-ELC',
                'category_id'      => $catSaldo->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supBinaAngkasa->id,
                'description'      => 'Voucher Saldo Elektronik',
                'stock_minimum'    => 10,
                'purchasing_price' => 1000,
                'selling_price'    => 5000,
            ]
        );

        $p_sewa_depo = Product::updateOrCreate(
            ['product_code' => '0087-P'],
            [
                'name'             => 'Sewa Depo',
                'category_id'      => $catAset->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supBinaAngkasa->id,
                'description'      => 'Sewa Depo MP3',
                'stock_minimum'    => 1,
                'purchasing_price' => 500000,
                'selling_price'    => 500000,
            ]
        );

        $p_filling = Product::updateOrCreate(
            ['product_code' => '0088-P'],
            [
                'name'             => 'Filling Cabinet',
                'category_id'      => $catAset->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supBinaAngkasa->id,
                'description'      => 'Filling Cabinet MP3',
                'stock_minimum'    => 1,
                'purchasing_price' => 500000,
                'selling_price'    => 500000,
            ]
        );

        $p_fingerprint = Product::updateOrCreate(
            ['product_code' => '0089-P'],
            [
                'name'             => 'Finger Print',
                'category_id'      => $catAset->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supElang->id,
                'description'      => 'Finger Print MP3',
                'stock_minimum'    => 1,
                'purchasing_price' => 150000,
                'selling_price'    => 150000,
            ]
        );

        $p_modem_hifi = Product::updateOrCreate(
            ['product_code' => '0090-P'],
            [
                'name'             => 'Modem Hifi Nokia',
                'category_id'      => $catAset->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supBinaMandiri->id,
                'description'      => 'Modem Hifi Nokia MP3',
                'stock_minimum'    => 1,
                'purchasing_price' => 250000,
                'selling_price'    => 250000,
            ]
        );

        $p_brangkas = Product::updateOrCreate(
            ['product_code' => '0091-P'],
            [
                'name'             => 'Brangkas',
                'category_id'      => $catAset->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supElang->id,
                'description'      => 'Brangkas MP3',
                'stock_minimum'    => 1,
                'purchasing_price' => 5000000,
                'selling_price'    => 5000000,
            ]
        );

        $p_modem_pool = Product::updateOrCreate(
            ['product_code' => '0092-P'],
            [
                'name'             => 'Modem Pool 16 Port',
                'category_id'      => $catAset->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supBinaAngkasa->id,
                'description'      => 'Modem Pool 16 Port MP3',
                'stock_minimum'    => 1,
                'purchasing_price' => 4500000,
                'selling_price'    => 4500000,
            ]
        );

        $p_total_asset = Product::updateOrCreate(
            ['product_code' => '0093-P'],
            [
                'name'             => 'Total Asset',
                'category_id'      => $catAset->id,
                'package_id'       => $pkgBox->id,
                'supplier_id'      => $supElang->id,
                'description'      => 'Total Asset MP3 PT Elang Digital Indonesia',
                'stock_minimum'    => 1,
                'purchasing_price' => 51500000,
                'selling_price'    => 51500000,
            ]
        );

        $p_hkm = Product::updateOrCreate(
            ['product_code' => '0094-P'],
            [
                'name'             => 'HKM 0127+',
                'category_id'      => $catAset->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supBinaAngkasa->id,
                'description'      => 'Modem 4G WIFI Router HKM 0127+',
                'stock_minimum'    => 1,
                'purchasing_price' => 368000,
                'selling_price'    => 368000,
            ]
        );

        $p_phy_9gb = Product::updateOrCreate(
            ['product_code' => '0095-P'],
            [
                'name'             => 'VO-PHY-9GB-7D',
                'category_id'      => $catVoucher->id,
                'package_id'       => $pkgBox->id,
                'supplier_id'      => $supBinaMandiri->id,
                'description'      => 'Voucher kosong 9GB 7 Hari',
                'stock_minimum'    => 10,
                'purchasing_price' => 500,
                'selling_price'    => 500,
            ]
        );

        $p_phy_3gb = Product::updateOrCreate(
            ['product_code' => '0096-P'],
            [
                'name'             => 'VO-PHY-3GB-3D',
                'category_id'      => $catVoucher->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supBinaAngkasa->id,
                'description'      => 'Voucher kosong 3GB 3 Hari',
                'stock_minimum'    => 10,
                'purchasing_price' => 500,
                'selling_price'    => 500,
            ]
        );

        $p_5gb = Product::updateOrCreate(
            ['product_code' => '0097-P'],
            [
                'name'             => 'VO 5GB-3D',
                'category_id'      => $catVoucher->id,
                'package_id'       => $pkgPcs->id,
                'supplier_id'      => $supBinaMandiri->id,
                'description'      => 'Voucher isi 5GB 3 Hari',
                'stock_minimum'    => 10,
                'purchasing_price' => 10900,
                'selling_price'    => 10900,
            ]
        );

        /*
         * ===========================
         *  ROLES & USERS
         * ===========================
         */

        $roleAdmin     = Role::where('slug', 'admin')->first();
        $roleWarehouse = Role::where('slug', 'warehouse')->first();
        $roleSales     = Role::where('slug', 'sales')->first();

        $admin = User::updateOrCreate(
            ['email' => 'admin@local'],
            [
                'name'         => 'Admin Pusat',
                'username'     => 'admin',
                'phone'        => '081200000001',
                'password'     => Hash::make('password123'),
                'warehouse_id' => null,
                'status'       => 'active',
            ]
        );
        if ($roleAdmin) {
            $admin->roles()->sync([$roleAdmin->id]);
        }

        $wh_bkt = User::updateOrCreate(
            ['email' => 'wh_bukittinggi@local'],
            [
                'name'         => 'Admin DEPO Bukittinggi',
                'username'     => 'wh_bukittinggi',
                'phone'        => '081200000002',
                'password'     => Hash::make('password123'),
                'warehouse_id' => $wh1->id,
                'status'       => 'active',
            ]
        );
        if ($roleWarehouse) {
            $wh_bkt->roles()->sync([$roleWarehouse->id]);
        }

        $wh_pdg = User::updateOrCreate(
            ['email' => 'wh_padang@local'],
            [
                'name'         => 'Admin DEPO Padang',
                'username'     => 'wh_padang',
                'phone'        => '081200000003',
                'password'     => Hash::make('password123'),
                'warehouse_id' => $wh2->id,
                'status'       => 'active',
            ]
        );
        if ($roleWarehouse) {
            $wh_pdg->roles()->sync([$roleWarehouse->id]);
        }

        $sales_bkt = User::updateOrCreate(
            ['email' => 'sales_bukittinggi@local'],
            [
                'name'         => 'Sales DEPO Bukittinggi',
                'username'     => 'sales_bukittinggi',
                'phone'        => '081200000004',
                'password'     => Hash::make('password123'),
                'warehouse_id' => $wh1->id,
                'status'       => 'active',
            ]
        );
        if ($roleSales) {
            $sales_bkt->roles()->sync([$roleSales->id]);
        }

        $sales_pdg = User::updateOrCreate(
            ['email' => 'sales_padang@local'],
            [
                'name'         => 'Sales DEPO Padang',
                'username'     => 'sales_padang',
                'phone'        => '081200000005',
                'password'     => Hash::make('password123'),
                'warehouse_id' => $wh2->id,
                'status'       => 'active',
            ]
        );
        if ($roleSales) {
            $sales_pdg->roles()->sync([$roleSales->id]);
        }

        /*
         * ===========================
         *  STOCK LEVELS
         * ===========================
         */

        $allProducts = [
            $p_vo_elc, $p_blank, $p_phy_9gb, $p_phy_3gb, $p_5gb,
            $p_hkm, $p_total_asset, $p_modem_pool, $p_brangkas,
            $p_modem_hifi, $p_fingerprint, $p_filling, $p_sewa_depo,
        ];

        foreach ($allProducts as $product) {
            if (!$product) {
                continue;
            }

            StockLevel::updateOrCreate(
                ['owner_type' => 'pusat', 'owner_id' => 0, 'product_id' => $product->id],
                ['quantity' => 100]
            );

            StockLevel::updateOrCreate(
                ['owner_type' => 'warehouse', 'owner_id' => $wh1->id, 'product_id' => $product->id],
                ['quantity' => 20]
            );
            StockLevel::updateOrCreate(
                ['owner_type' => 'warehouse', 'owner_id' => $wh2->id, 'product_id' => $product->id],
                ['quantity' => 25]
            );

            StockLevel::updateOrCreate(
                ['owner_type' => 'sales', 'owner_id' => $sales_bkt->id, 'product_id' => $product->id],
                ['quantity' => 10]
            );
            StockLevel::updateOrCreate(
                ['owner_type' => 'sales', 'owner_id' => $sales_pdg->id, 'product_id' => $product->id],
                ['quantity' => 12]
            );
        }
    }
}
