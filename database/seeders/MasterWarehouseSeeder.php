<?php
 
namespace Database\Seeders;
 
use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Log;
 
class MasterWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $csvFile = database_path('seeders/csv/warehouses_master.csv');
 
        if (!file_exists($csvFile)) {
            Log::warning("File CSV Warehouse tidak ditemukan di: $csvFile");
            return;
        }
 
        $this->command->info('Memulai import Warehouse dari CSV...');
 
        if (($handle = fopen($csvFile, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ',');
 
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $row = array_combine($header, $data);
 
                try {
                    Warehouse::updateOrCreate(
                        ['warehouse_code' => $row['warehouse_code']],
                        [
                            'id'             => $row['id'] ?? null,
                            'warehouse_name' => $row['warehouse_name'],
                            'address'        => $row['address'],
                            'note'           => $row['note']
                        ]
                    );
                } catch (\Exception $e) {
                    $this->command->error('Error baris: ' . ($row['warehouse_code'] ?? 'Unknown') . ' - ' . $e->getMessage());
                }
            }
            fclose($handle);
        }
 
        $this->command->info('Import Warehouse selesai.');
    }
}
