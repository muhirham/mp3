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
                Company::updateOrCreate(
            ['code' => 'IOH'], // sesuai KODE di form
            [
                'name'            => 'IOH',
                'legal_name'      => 'Indosat Ooredoo Hutchison',
                'short_name'      => 'IOH',
                'address'         => 'Jl. Medan Merdeka Barat No.21, RW.3, Gambir, Kecamatan Gambir, Kota Jakarta Pusat, Indonesia',
                'city'            => 'Jakarta',
                'province'        => 'Jakarta Pusat',
                'phone'           => '08116027598',
                'email'           => 'care@im3.id',
                'website'         => 'https://ioh.co.id/portal/id/iohindex',
                'tax_number'      => null,
                'logo_path'       => 'ImageAsset/logo-indosat.png', // isi kalau sudah upload
                'logo_small_path' => null,
                'is_default'      => false,
                'is_active'       => true,
            ]
        );
    }
}
