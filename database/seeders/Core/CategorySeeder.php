<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::updateOrCreate(
            ['category_code' => 'CAT-VCR'],
            ['category_name' => 'Voucher', 'description' => 'Produk voucher data / fisik']
        );

        Category::updateOrCreate(
            ['category_code' => 'CAT-SLD'],
            ['category_name' => 'Saldo Elektronik', 'description' => 'Produk saldo elektronik']
        );

        Category::updateOrCreate(
            ['category_code' => 'CAT-KPK'],
            ['category_name' => 'Kartu', 'description' => 'Produk kartu pedana kosong']
        );

        Category::updateOrCreate(
            ['category_code' => 'CAT-AST'],
            ['category_name' => 'Aset & Perangkat', 'description' => 'Modem, brankas, dll']
        );
    }
}