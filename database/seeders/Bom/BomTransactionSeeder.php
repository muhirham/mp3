<?php

namespace Database\Seeders\Bom;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Bom;
use App\Models\User;
use App\Models\BomTransaction;

class BomTransactionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('bom_transactions')) return;

        $bom = Bom::first();
        $user = User::first();

        if (!$bom || !$user) return;

        BomTransaction::updateOrCreate(
            [
                'bom_id'     => $bom->id,
                'product_id' => $bom->product_id,
            ],
            [
                'production_qty' => 5, // produksi 5 batch
                'total_cost'     => 0, // nanti dihitung di item
                'user_id'        => $user->id,
            ]
        );
    }
}