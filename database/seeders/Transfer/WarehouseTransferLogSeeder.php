<?php

namespace Database\Seeders\Transfer;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\WarehouseTransfer;
use App\Models\User;

class WarehouseTransferLogSeeder extends Seeder
{
    public function run(): void
    {
        $transfer = WarehouseTransfer::first();
        $admin    = User::first();

        if (!$transfer || !$admin) return;

        DB::table('warehouse_transfer_logs')->insert([
            'warehouse_transfer_id' => $transfer->id,
            'action'                => 'CREATED',
            'performed_by'          => $admin->id,
            'note'                  => 'Full testing log entry',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $this->command->info('WarehouseTransferLog FULL TEST created.');
    }
}