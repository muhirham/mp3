<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;

use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\StockLevel;
use App\Models\Package;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $sales_bkt = User::where('username', 'sales_bukittinggi')->firstOrFail();
        $sales_pdg = User::where('username', 'sales_padang')->firstOrFail();
        /*
         * ===========================
         *  SUPPLIER (REAL)
         *  - sesuai request: cuma IOH
         * ===========================
         */
        $supIOH = Supplier::updateOrCreate(
            ['supplier_code' => 'SUP-0001'],
            [
                'name'         => 'PT Indosat Ooredoo Hutchison Tbk (IOH)',
                'address'      => null,
                'phone'        => null,
                'note'         => 'Supplier utama (IOH)',
                'bank_name'    => null,
                'bank_account' => null,
            ]
        );

        /*
         * ===========================
         *  PACKAGES (UOM)
         * ===========================
         */
        $pkgBox = Package::updateOrCreate(['package_name' => 'BOX'], ['package_name' => 'BOX']);
        $pkgPcs = Package::updateOrCreate(['package_name' => 'PCS'], ['package_name' => 'PCS']);
        $pkgRp  = Package::updateOrCreate(['package_name' => 'Rp.'], ['package_name' => 'Rp.']);

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

        Warehouse::updateOrCreate(['warehouse_code' => 'DEPO-DHARMASRAYA'], [
            'warehouse_name' => 'DEPO DHARMASRAYA',
            'address'        => 'DEPO DHARMASRAYA',
            'note'           => 'Cabang DEPO Dharmasraya',
        ]);

        Warehouse::updateOrCreate(['warehouse_code' => 'DEPO-PARIAMAN'], [
            'warehouse_name' => 'DEPO PARIAMAN',
            'address'        => 'DEPO PARIAMAN',
            'note'           => 'Cabang DEPO Pariaman',
        ]);

        Warehouse::updateOrCreate(['warehouse_code' => 'DEPO-PASAMAN-BARAT'], [
            'warehouse_name' => 'DEPO PASAMAN BARAT',
            'address'        => 'DEPO PASAMAN BARAT',
            'note'           => 'Cabang DEPO Pasaman Barat',
        ]);

        Warehouse::updateOrCreate(['warehouse_code' => 'DEPO-PAYAKUMBUH'], [
            'warehouse_name' => 'DEPO PAYAKUMBUH',
            'address'        => 'DEPO PAYAKUMBUH',
            'note'           => 'Cabang DEPO Payakumbuh',
        ]);

        Warehouse::updateOrCreate(['warehouse_code' => 'DEPO-PESISIR-SELATAN'], [
            'warehouse_name' => 'DEPO PESISIR SELATAN',
            'address'        => 'DEPO PESISIR SELATAN',
            'note'           => 'Cabang DEPO Pesisir Selatan',
        ]);

        Warehouse::updateOrCreate(['warehouse_code' => 'DEPO-SOLOK'], [
            'warehouse_name' => 'DEPO SOLOK',
            'address'        => 'DEPO SOLOK',
            'note'           => 'Cabang DEPO Solok',
        ]);

        Warehouse::updateOrCreate(['warehouse_code' => 'DEPO-TANAH-DATAR'], [
            'warehouse_name' => 'DEPO TANAH DATAR',
            'address'        => 'DEPO TANAH DATAR',
            'note'           => 'Cabang DEPO Tanah Datar',
        ]);

        /*
         * ===========================
         *  CATEGORIES
         * ===========================
         */
        $catVoucher = Category::updateOrCreate(
            ['category_code' => 'CAT-VCR'],
            ['category_name' => 'Voucher', 'description' => 'Produk voucher data / fisik']
        );

        $catSaldo = Category::updateOrCreate(
            ['category_code' => 'CAT-SLD'],
            ['category_name' => 'Saldo Elektronik', 'description' => 'Produk saldo elektronik']
        );

        $catKpk = Category::updateOrCreate(
            ['category_code' => 'CAT-KPK'],
            ['category_name' => 'Kartu', 'description' => 'Produk kartu pedana kosong']
        );

        $catAset = Category::updateOrCreate(
            ['category_code' => 'CAT-AST'],
            ['category_name' => 'Aset & Perangkat', 'description' => 'Modem, brankas, filling cabinet, sewa depo, dll.']
        );

        /*
         * ===========================
         *  PRODUCTS
         *  - supplier_id disamakan ke IOH (konsekuensi supplier cuma IOH)
         * ===========================
         */
        $p_blank = Product::updateOrCreate(['product_code' => '0079-P'], [
            'name'             => 'VO-BLANK-MINI3-CSS',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgBox->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher Fisik Kosong',
            'stock_minimum'    => 10,
            'purchasing_price' => 500,
            'selling_price'    => 500,
        ]);
        $p_Sld = Product::updateOrCreate(['product_code' => '0001-P'], [
            'name'             => 'Saldo Pulsa',
            'category_id'      => $catSaldo->id,
            'package_id'       => $pkgRp->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Saldo Pulsa Elektronik',
            'stock_minimum'    => 1000000,
            'purchasing_price' => 1,
            'selling_price'    => 1,
        ]);
        $p_kpk = Product::updateOrCreate(['product_code' => '0002-P'], [
            'name'             => 'Kartu Perdana Kosong',
            'category_id'      => $catKpk->id,
            'package_id'       => $pkgRp->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Stock Kartu Perdana Kosong',
            'stock_minimum'    => 500,
            'purchasing_price' => 1000,
            'selling_price'    => 1000,
        ]);

        $p_vo_elc = Product::updateOrCreate(['product_code' => '0081-P'], [
            'name'             => 'VO-ELC',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher Saldo Elektronik',
            'stock_minimum'    => 10,
            'purchasing_price' => 1000,
            'selling_price'    => 5000,
        ]);

        $p_sewa_depo = Product::updateOrCreate(['product_code' => '0087-P'], [
            'name'             => 'Sewa Depo',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Sewa Depo MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 500000,
            'selling_price'    => 500000,
        ]);

        $p_filling = Product::updateOrCreate(['product_code' => '0088-P'], [
            'name'             => 'Filling Cabinet',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Filling Cabinet MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 500000,
            'selling_price'    => 500000,
        ]);

        $p_fingerprint = Product::updateOrCreate(['product_code' => '0089-P'], [
            'name'             => 'Finger Print',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Finger Print MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 150000,
            'selling_price'    => 150000,
        ]);

        $p_modem_hifi = Product::updateOrCreate(['product_code' => '0090-P'], [
            'name'             => 'Modem Hifi Nokia',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Modem Hifi Nokia MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 250000,
            'selling_price'    => 250000,
        ]);

        $p_brangkas = Product::updateOrCreate(['product_code' => '0091-P'], [
            'name'             => 'Brangkas',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Brangkas MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 5000000,
            'selling_price'    => 5000000,
        ]);

        $p_modem_pool = Product::updateOrCreate(['product_code' => '0092-P'], [
            'name'             => 'Modem Pool 16 Port',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Modem Pool 16 Port MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 4500000,
            'selling_price'    => 4500000,
        ]);

        $p_total_asset = Product::updateOrCreate(['product_code' => '0093-P'], [
            'name'             => 'Total Asset',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgBox->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Total Asset',
            'stock_minimum'    => 1,
            'purchasing_price' => 51500000,
            'selling_price'    => 51500000,
        ]);

        $p_hkm = Product::updateOrCreate(['product_code' => '0094-P'], [
            'name'             => 'HKM 0127+',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Modem 4G WIFI Router HKM 0127+',
            'stock_minimum'    => 1,
            'purchasing_price' => 368000,
            'selling_price'    => 368000,
        ]);

        $p_phy_9gb = Product::updateOrCreate(['product_code' => '0095-P'], [
            'name'             => 'VO-PHY-9GB-7D',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgBox->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher kosong 9GB 7 Hari',
            'stock_minimum'    => 10,
            'purchasing_price' => 500,
            'selling_price'    => 500,
        ]);

        $p_phy_3gb = Product::updateOrCreate(['product_code' => '0096-P'], [
            'name'             => 'VO-PHY-3GB-3D',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher kosong 3GB 3 Hari',
            'stock_minimum'    => 10,
            'purchasing_price' => 500,
            'selling_price'    => 500,
        ]);

        $p_5gb = Product::updateOrCreate(['product_code' => '0097-P'], [
            'name'             => 'VO 5GB-3D',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher isi 5GB 3 Hari',
            'stock_minimum'    => 10,
            'purchasing_price' => 10900,
            'selling_price'    => 10900,
        ]);


        /*
         * ===========================
         *  STOCK LEVELS
         *  (gue biarin seperti konsep lu, bukan demo PO dll)
         * ===========================
         */
        $allProducts = [
            $p_vo_elc,$p_Sld,$p_kpk, $p_blank, $p_phy_9gb, $p_phy_3gb, $p_5gb,
            $p_hkm, $p_total_asset, $p_modem_pool, $p_brangkas,
            $p_modem_hifi, $p_fingerprint, $p_filling, $p_sewa_depo,
        ];

        foreach ($allProducts as $product) {
            if (! $product) continue;

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
