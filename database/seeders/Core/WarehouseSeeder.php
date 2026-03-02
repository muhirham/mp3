<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            'DEPO-BUKITTINGGI',
            'DEPO-PADANG',
            'DEPO-DHARMASRAYA',
            'DEPO-PARIAMAN',
            'DEPO-PASAMAN-BARAT',
            'DEPO-PAYAKUMBUH',
            'DEPO-PESISIR-SELATAN',
            'DEPO-SOLOK',
            'DEPO-TANAH-DATAR',
        ];

        foreach ($warehouses as $code) {
            Warehouse::updateOrCreate(
                ['warehouse_code' => $code],
                [
                    'warehouse_name' => str_replace('-', ' ', $code),
                    'address'        => str_replace('-', ' ', $code),
                    'note'           => 'Cabang ' . str_replace('-', ' ', $code),
                ]
            );
        }
    }
}