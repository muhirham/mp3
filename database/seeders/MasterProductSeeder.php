<?php
 
namespace Database\Seeders;
 
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
 
class MasterProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Instruksi:
     * 1. Export CSV dari menu Products (Tombol Export Seeder).
     * 2. Simpan file tersebut di database/seeders/csv/products_master.csv.
     * 3. Jalankan: php artisan db:seed --class=MasterProductSeeder
     */
    public function run(): void
    {
        $path = database_path('seeders/csv/products_master.csv');
 
        if (!file_exists($path)) {
            $this->command->error("Gagal: File CSV tidak ditemukan di $path");
            $this->command->warn("Silakan download CSV dari menu Products dan letakkan di folder tersebut dengan nama 'products_master.csv'");
            return;
        }
 
        $file = fopen($path, 'r');
        $header = fgetcsv($file);
 
        if (!$header) {
            $this->command->error("Gagal: File CSV kosong atau format salah.");
            return;
        }
 
        $this->command->info("Memulai import produk dari CSV...");
 
        DB::beginTransaction();
        try {
            $count = 0;
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($header, $row);
 
                // Gunakan updateOrCreate berdasarkan product_code
                Product::updateOrCreate(
                    ['product_code' => $data['product_code']],
                    [
                        'id'               => $data['id'],
                        'name'             => $data['name'],
                        'category_id'      => !empty($data['category_id']) ? $data['category_id'] : null,
                        'product_type'     => $data['product_type'] ?? 'normal',
                        'description'      => $data['description'] ?? null,
                        'purchasing_price' => $data['purchasing_price'] ?? 0,
                        'selling_price'    => $data['selling_price'] ?? 0,
                        'standard_cost'    => $data['standard_cost'] ?? 0,
                        'stock_minimum'    => $data['stock_minimum'] ?? 0,
                        'supplier_id'      => !empty($data['supplier_id']) ? $data['supplier_id'] : null,
                        'package_id'       => !empty($data['package_id']) ? $data['package_id'] : null,
                        'is_active'        => ($data['is_active'] == 1),
                    ]
                );
 
                $count++;
            }
 
            DB::commit();
            $this->command->info("Berhasil mengimpor $count produk master!");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Terjadi kesalahan saat import: " . $e->getMessage());
            $this->command->error("Detail: baris data mungkin tidak cocok dengan header.");
        }
 
        fclose($file);
    }
}
