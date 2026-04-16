<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Events\SalesReturnUpdated;

// --- CONFIG TEST DATA ---
$warehouseId = 1;      // Sesuaikan sama warehouse lo saat ini
$salesId     = 5;      // Sesuaikan sama user id lo saat ini
$handoverId  = 999;    // Dummy HDO ID
$type        = 'status_updated';

echo "Memulai Test Broadcast Reverb...\n";
echo "Target Warehouse: $warehouseId\n";
echo "Target Sales: $salesId\n";

try {
    // Tembak event
    broadcast(new SalesReturnUpdated($warehouseId, $salesId, $handoverId, $type, 'Bot Tester'));
    
    echo "SUCCESS: Event 'SalesReturnUpdated' sudah dikirim ke Reverb!\n";
    echo "Cek Console F12 di browser lo sekarang cok.\n";
} catch (\Exception $e) {
    echo "ERROR: Gagal kirim broadcast. Pesan: " . $e->getMessage() . "\n";
}
