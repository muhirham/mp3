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
            // Fallback statis kalau config/menu.php belum kepasang
            $adminKeys = [
                'products', 'packages', 'categories', 'suppliers',
                'stock_adjustments',
                'warehouses', 'wh_stocklevel', 'wh_restock',
                'goodreceived', 'wh_issue', 'wh_reconcile', 'wh_sales_reports',
                'sales_daily', 'sales_return',
                'po', 'restock_request_ap', 'company',
                'users', 'roles',
            ];

            $warehouseKeys = [
                'wh_restock', 'warehouses', 'wh_stocklevel',
                'goodreceived', 'wh_issue', 'wh_reconcile', 'wh_sales_reports',
            ];

            $salesKeys = [
                'sales_daily', 'sales_return',
            ];
        } else {
            // Ambil dari registry menu
            $adminKeys = $items->pluck('key')->unique()->values()->all();
            $warehouseKeys = $items->where('group', 'warehouse')
                ->pluck('key')->unique()->values()->all();
            $salesKeys = $items->where('group', 'sales')
                ->pluck('key')->unique()->values()->all();
        }

        $rows = [
            [
                'slug'       => 'superadmin',
                'name'       => 'Administrator',
                'home_route' => 'admin.dashboard',
                'menu_keys'  => $adminKeys,
            ],
            [
                'slug'       => 'warehouse',
                'name'       => 'Warehouse',
                'home_route' => 'warehouse.dashboard',
                'menu_keys'  => $warehouseKeys,
            ],
            [
                'slug'       => 'sales',
                'name'       => 'Sales',
                'home_route' => 'sales.dashboard',
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
