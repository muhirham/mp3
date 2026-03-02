<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */
        $basicRoles = [
            'superadmin'  => ['name' => 'Super Admin'],
            'admin'       => ['name' => 'Admin'],
            'warehouse'   => ['name' => 'Warehouse'],
            'procurement' => ['name' => 'Procurement'],
            'ceo'         => ['name' => 'CEO'],
            'sales'       => ['name' => 'Sales'],
        ];

        foreach ($basicRoles as $slug => $data) {
            Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $data['name']]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | MENU KEYS
        |--------------------------------------------------------------------------
        */
        $items   = collect(config('menu.items', []));
        $allKeys = $items->pluck('key')->filter()->values()->all();

        $pickByOrder = function (array $wanted) use ($allKeys) {
            return array_values(array_intersect($allKeys, $wanted));
        };

        $warehouseKeys = $pickByOrder(array_merge(
            $items->where('group', 'warehouse')->pluck('key')->all(),
            ['wh_restock','users']
        ));

        $salesKeys = $items->where('group', 'sales')->pluck('key')->all();

        $procurementKeys = $pickByOrder([
            'products','packages','categories','suppliers',
            'warehouses','wh_restock','restock_request_ap','po','company',
        ]);

        $ceoKeys = $pickByOrder([
            'po','wh_sales_reports','reports','company',
        ]);

        $roleMenuRows = [
            ['slug'=>'superadmin','home_route'=>'admin.dashboard','menu_keys'=>$allKeys],
            ['slug'=>'admin','home_route'=>'admin.dashboard','menu_keys'=>$allKeys],
            ['slug'=>'warehouse','home_route'=>'warehouse.dashboard','menu_keys'=>$warehouseKeys],
            ['slug'=>'sales','home_route'=>'sales.dashboard','menu_keys'=>$salesKeys],
            ['slug'=>'procurement','home_route'=>'admin.dashboard','menu_keys'=>$procurementKeys],
            ['slug'=>'ceo','home_route'=>'admin.dashboard','menu_keys'=>$ceoKeys],
        ];

        foreach ($roleMenuRows as $row) {
            Role::where('slug', $row['slug'])->update([
                'home_route' => $row['home_route'],
                'menu_keys'  => $row['menu_keys'],
            ]);
        }
    }
}