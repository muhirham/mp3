<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $items = collect(config('menu.items', []));

        if ($items->isEmpty()) {
            // fallback statis kalau config/menu.php belum kepasang
            $adminKeys = [
                'warehouses','categories','suppliers','packages','products',
                'restock_request_ap','po','transactions','reports','users','roles',
            ];

            $warehouseKeys = [
                'wh_stocklevel','wh_restock','wh_issue','wh_reconcile','wh_sales_reports',
            ];

            $salesKeys = [
                'sales_daily','sales_return',
            ];
        } else {
            // ambil dari registry menu
            $adminKeys     = $items->pluck('key')->unique()->values()->all();
            $warehouseKeys = $items->where('group', 'warehouse')->pluck('key')->unique()->values()->all();
            $salesKeys     = $items->where('group', 'sales')->pluck('key')->unique()->values()->all();
        }

        $rows = [
            [
                'slug'       => 'admin',
                'name'       => 'Administrator',
                'home_route' => 'admin.dashboard',      // <- landing page admin
                'menu_keys'  => $adminKeys,
            ],
            [
                'slug'       => 'warehouse',
                'name'       => 'Warehouse',
                'home_route' => 'warehouse.dashboard',  // <- landing page warehouse
                'menu_keys'  => $warehouseKeys,
            ],
            [
                'slug'       => 'sales',
                'name'       => 'Sales',
                'home_route' => 'sales.dashboard',      // <- landing page sales
                'menu_keys'  => $salesKeys,
            ],
        ];

        foreach ($rows as $data) {
            Role::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
