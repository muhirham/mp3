<?php

namespace Database\Seeders\System;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ActivityLog;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        if (!$admin || !\Schema::hasTable((new ActivityLog)->getTable())) return;

        ActivityLog::updateOrCreate(
            [
                'user_id'     => $admin->id,
                'action'      => 'Seeder Init',
                'entity_type' => 'System',
            ],
            [
                'entity_id'   => null,
                'description' => 'Seeder initial setup (real testing data) berhasil dibuat.',
            ]
        );
    }
}