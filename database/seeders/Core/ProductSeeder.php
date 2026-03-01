<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Package;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $supIOH = Supplier::where('supplier_code', 'SUP-0001')->firstOrFail();

        $catVoucher = Category::where('category_code', 'CAT-VCR')->firstOrFail();
        $catSaldo   = Category::where('category_code', 'CAT-SLD')->firstOrFail();
        $catKpk     = Category::where('category_code', 'CAT-KPK')->firstOrFail();
        $catAset    = Category::where('category_code', 'CAT-AST')->firstOrFail();

        $pkgBox = Package::where('package_name', 'BOX')->firstOrFail();
        $pkgPcs = Package::where('package_name', 'PCS')->firstOrFail();
        $pkgRp  = Package::where('package_name', 'Rp.')->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | PRODUCTS (REAL DATA - PRE PRODUCTION)
        |--------------------------------------------------------------------------
        */

        Product::updateOrCreate(['product_code' => '0079-P'], [
            'name'             => 'VO-BLANK-MINI3-CSS',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgBox->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher Fisik Kosong',
            'stock_minimum'    => 10,
            'purchasing_price' => 500,
            'selling_price'    => 500,
            'product_type'     => 'BOM',
            'standard_cost'    => 500.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0001-P'], [
            'name'             => 'Saldo Pulsa',
            'category_id'      => $catSaldo->id,
            'package_id'       => $pkgRp->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Saldo Pulsa Elektronik',
            'stock_minimum'    => 1000000,
            'purchasing_price' => 1,
            'selling_price'    => 1,
            'product_type'     => 'material',
            'standard_cost'    => 1.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0002-P'], [
            'name'             => 'Kartu Perdana Kosong',
            'category_id'      => $catKpk->id,
            'package_id'       => $pkgRp->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Stock Kartu Perdana Kosong',
            'stock_minimum'    => 500,
            'purchasing_price' => 1000,
            'selling_price'    => 1000,
            'product_type'     => 'material',
            'standard_cost'    => 1000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0081-P'], [
            'name'             => 'VO-ELC',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher Saldo Elektronik',
            'stock_minimum'    => 10,
            'purchasing_price' => 1000,
            'selling_price'    => 5000,
            'product_type'     => 'BOM',
            'standard_cost'    => 1000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0087-P'], [
            'name'             => 'Sewa Depo',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Sewa Depo MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 500000,
            'selling_price'    => 500000,
            'product_type'     => 'normal',
            'standard_cost'    => 500000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0088-P'], [
            'name'             => 'Filling Cabinet',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Filling Cabinet MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 500000,
            'selling_price'    => 500000,
            'product_type'     => 'normal',
            'standard_cost'    => 500000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0089-P'], [
            'name'             => 'Finger Print',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Finger Print MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 150000,
            'selling_price'    => 150000,
            'product_type'     => 'normal',
            'standard_cost'    => 150000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0090-P'], [
            'name'             => 'Modem Hifi Nokia',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Modem Hifi Nokia MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 250000,
            'selling_price'    => 250000,
            'product_type'     => 'normal',
            'standard_cost'    => 250000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0091-P'], [
            'name'             => 'Brangkas',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Brangkas MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 5000000,
            'selling_price'    => 5000000,
            'product_type'     => 'normal',
            'standard_cost'    => 5000000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0092-P'], [
            'name'             => 'Modem Pool 16 Port',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Modem Pool 16 Port MP3',
            'stock_minimum'    => 1,
            'purchasing_price' => 4500000,
            'selling_price'    => 4500000,
            'product_type'     => 'normal',
            'standard_cost'    => 4500000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0093-P'], [
            'name'             => 'Total Asset',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgBox->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Total Asset',
            'stock_minimum'    => 1,
            'purchasing_price' => 51500000,
            'selling_price'    => 51500000,
            'product_type'     => 'normal',
            'standard_cost'    => 51500000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0094-P'], [
            'name'             => 'HKM 0127+',
            'category_id'      => $catAset->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Modem 4G WIFI Router HKM 0127+',
            'stock_minimum'    => 1,
            'purchasing_price' => 368000,
            'selling_price'    => 368000,
            'product_type'     => 'normal',
            'standard_cost'    => 368000.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0095-P'], [
            'name'             => 'VO-PHY-9GB-7D',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgBox->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher kosong 9GB 7 Hari',
            'stock_minimum'    => 10,
            'purchasing_price' => 500,
            'selling_price'    => 500,
            'product_type'     => 'BOM',
            'standard_cost'    => 500.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0096-P'], [
            'name'             => 'VO-PHY-3GB-3D',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher kosong 3GB 3 Hari',
            'stock_minimum'    => 10,
            'purchasing_price' => 500,
            'selling_price'    => 500,
            'product_type'     => 'BOM',
            'standard_cost'    => 500.00,
            'is_active'        => true,
        ]);

        Product::updateOrCreate(['product_code' => '0097-P'], [
            'name'             => 'VO 5GB-3D',
            'category_id'      => $catVoucher->id,
            'package_id'       => $pkgPcs->id,
            'supplier_id'      => $supIOH->id,
            'description'      => 'Voucher isi 5GB 3 Hari',
            'stock_minimum'    => 10,
            'purchasing_price' => 10900,
            'selling_price'    => 10900,
            'product_type'     => 'BOM',
            'standard_cost'    => 10900.00,
            'is_active'        => true,
        ]);
    }
}