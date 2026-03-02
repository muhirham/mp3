<?php

namespace Database\Seeders\Transfer;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Warehouse;
use App\Models\User;

class WarehouseTransferSeeder extends Seeder
{
    public function run(): void
    {
        $source = Warehouse::first();
        $destination = Warehouse::where('id', '!=', $source?->id)->first();
        $user = User::first();

        if (!$source || !$destination || !$user) {
            $this->command->warn('Warehouse/User tidak cukup untuk seed transfer.');
            return;
        }

        DB::table('warehouse_transfers')->updateOrInsert(
            [
                'transfer_code' => 'TRF-FULLTEST-001',
            ],
            [
                'source_warehouse_id'      => $source->id,
                'destination_warehouse_id' => $destination->id,
                'status'                   => 'approved',
                'total_cost'               => 50000,

                'created_by'               => $user->id,

                // SESUAI MIGRATION LU
                'approved_destination_by'  => $user->id,
                'approved_destination_at'  => now(),

                'gr_source_by'             => $user->id,
                'gr_source_at'             => now(),

                'note'                     => 'Seeder FULL TEST sesuai migration',

                'created_at'               => now(),
                'updated_at'               => now(),
            ]
        );

        $this->command->info('WarehouseTransfer FULL TEST created.');
    }
}