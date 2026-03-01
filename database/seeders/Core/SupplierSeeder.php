<?php
namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::updateOrCreate(
            ['supplier_code' => 'SUP-0001'],
            [
                'name' => 'PT Indosat Ooredoo Hutchison Tbk (IOH)',
                'address' => 'Jln Raya Medan,Jakarta',
                'phone' => '085632351489',
                'note' => 'Supplier utama (IOH)',
                'bank_name' => 'BCA',
                'bank_account' => '2026345',
            ]
        );
    }
}