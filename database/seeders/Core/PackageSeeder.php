<?php
namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        Package::updateOrCreate(['package_name' => 'BOX'], ['package_name' => 'BOX']);
        Package::updateOrCreate(['package_name' => 'PCS'], ['package_name' => 'PCS']);
        Package::updateOrCreate(['package_name' => 'Rp.'], ['package_name' => 'Rp.']);
    }
}