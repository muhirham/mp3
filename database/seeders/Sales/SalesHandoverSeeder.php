<?php

namespace Database\Seeders\Sales;

use Illuminate\Database\Seeder;
use App\Models\SalesHandover;
use App\Models\User;
use App\Models\Warehouse;

class SalesHandoverSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();
        $sales     = User::first();
        $admin     = User::first();

        if (!$warehouse || !$sales || !$admin) {
            $this->command->warn('Missing dependency for SalesHandoverSeeder');
            return;
        }

        SalesHandover::updateOrCreate(
            ['code' => 'HDO-FULLTEST-001'],
            [
                'warehouse_id' => $warehouse->id,
                'sales_id'     => $sales->id,
                'issued_by'    => $admin->id,
                'closed_by'    => $admin->id,
                'handover_date'=> now(),
                'status'       => 'closed',

                'total_dispatched_amount' => 100000,
                'total_sold_amount'       => 80000,
                'cash_amount'             => 80000,
                'transfer_amount'         => 0,
                'discount_total'          => 5000,
                'grand_total'             => 75000,

                'evening_otp_verified_at' => now(),
            ]
        );

        $this->command->info('SalesHandover FULL TEST created.');
    }
}