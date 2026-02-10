<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;

class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */
        $basicRoles = [
            'superadmin'  => ['name' => 'Super Admin',  'photo' => 'ImageAsset/1.jpg'],
            'admin'       => ['name' => 'Admin',        'photo' => 'ImageAsset/2.jpg'],
            'warehouse'   => ['name' => 'Warehouse',    'photo' => 'ImageAsset/3.jpg'],
            'procurement' => ['name' => 'Procurement',  'photo' => 'ImageAsset/4.jpg'],
            'ceo'         => ['name' => 'CEO',           'photo' => 'ImageAsset/5.jpg'],
            'sales'       => ['name' => 'Sales',         'photo' => 'ImageAsset/3.jpg'],
        ];

        foreach ($basicRoles as $slug => $data) {
            Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'  => $data['name'],
                ]
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

        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        $wh1 = Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-BUKITTINGGI'],
            ['warehouse_name' => 'DEPO BUKITTINGGI']
        );

        $wh2 = Warehouse::updateOrCreate(
            ['warehouse_code' => 'DEPO-PADANG'],
            ['warehouse_name' => 'DEPO PADANG']
        );

        $roles = Role::pluck('id','slug');

        $admin = User::updateOrCreate(
            ['email' => 'admin@local'],
            [
                'name'     => 'Admin Pusat',
                'username' => 'admin',
                'phone'    => '081200000001',
                'password' => Hash::make('password123'),
                'signature_path' => 'ImageAsset/1.jpg',
                'status'   => 'active',
            ]
        );
        $admin->roles()->sync([$roles['superadmin'] ?? $roles['admin']]);

            $users = [
        [
            'email' => 'wh_bukittinggi@local',
            'username' => 'wh_bukittinggi',
            'name' => 'Admin DEPO Bukittinggi',
            'position' => 'Warehouse Admin',
            'signature' => 'ImageAsset/3.jpg',
            'role' => 'warehouse',
            'warehouse' => $wh1,
        ],
        [
            'email' => 'wh_padang@local',
            'username' => 'wh_padang',
            'name' => 'Admin DEPO Padang',
            'position' => 'Warehouse Admin',
            'signature' => 'ImageAsset/3.jpg',
            'role' => 'warehouse',
            'warehouse' => $wh2,
        ],
        [
            'email' => 'sales_bukittinggi@local',
            'username' => 'sales_bukittinggi',
            'name' => 'Sales DEPO Bukittinggi',
            'position' => 'Sales',
            'signature' => 'ImageAsset/5.jpg',
            'role' => 'sales',
            'warehouse' => $wh1,
        ],
        [
            'email' => 'sales_padang@local',
            'username' => 'sales_padang',
            'name' => 'Sales DEPO Padang',
            'position' => 'Sales',
            'signature' => 'ImageAsset/5.jpg',
            'role' => 'sales',
            'warehouse' => $wh2,
        ],
        [
            'email' => 'sales_padang@loscal',
            'username' => 'Rudi',
            'name' => 'Sales Padang Rudi',
            'position' => 'Sales',
            'signature' => 'ImageAsset/5.jpg',
            'role' => 'sales',
            'warehouse' => $wh2,
        ],
        [
            'email' => 'procurement@local',
            'username' => 'procurement',
            'name' => 'User Procurement',
            'position' => 'Procurement',
            'signature' => 'ImageAsset/4.jpg',
            'role' => 'procurement',
            'warehouse' => null,
        ],
        [
            'email' => 'ceo@local',
            'username' => 'ceo',
            'name' => 'Chief Executive Officer',
            'position' => 'CEO',
            'signature' => 'ImageAsset/1.jpg',
            'role' => 'ceo',
            'warehouse' => null,
        ],
    ];


    foreach ($users as $i => $data) {
        $u = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name'           => $data['name'],
                'username'       => $data['username'],
                'phone'          => '08120000' . str_pad($i+10, 4, '0', STR_PAD_LEFT),
                'password'       => Hash::make('password123'),
                'position'       => $data['position'],
                'signature_path' => $data['signature'],
                'warehouse_id'   => $data['warehouse']?->id,
                'status'         => 'active',
            ]
        );

        if (isset($roles[$data['role']])) {
            $u->roles()->sync([$roles[$data['role']]]);
        }
        }
    }
}
