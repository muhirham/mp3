<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        //
        Company::updateOrCreate(
            ['code' => 'MANDAU'],
            [
                'name'            => 'Mandau',
                'legal_name'      => 'PT Mandiri Daya Utama Nusantara',
                'short_name'      => 'MANDAU',
                'address'         => 'Komplek Golden Plaza Blok C17, Jl. RS Fatmawati No. 15',
                'city'            => 'Jakarta Selatan',
                'province'        => 'DKI Jakarta',
                'phone'           => '+62 21 7590 9945',
                'email'           => 'info@mandau.id',
                'website'         => 'https://mandau.id',
                'tax_number'      => null,
                'logo_path'       => 'ImageAsset/logo-mandau.png', // ⬅️ boleh isi langsung
                'logo_small_path' => null,
                'is_default'      => true,
                'is_active'       => true,
            ]
        );
    }
}
