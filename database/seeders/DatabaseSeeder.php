<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// ⬇️ INI WAJIB
use Database\Seeders\Core\CompanySeeder;
use Database\Seeders\Core\RoleUserSeeder;
use Database\Seeders\Core\CoreSeeder;
use Database\Seeders\OperationSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            RoleUserSeeder::class,
            CoreSeeder::class,
            OperationSeeder::class,
        ]);
    }
}
