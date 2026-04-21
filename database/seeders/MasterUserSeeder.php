<?php
 
namespace Database\Seeders;
 
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
 
class MasterUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Instruksi:
     * 1. Export CSV dari aplikasi web (Tombol Export Seeder).
     * 2. Simpan file tersebut di database/seeders/csv/users_master.csv.
     * 3. Jalankan: php artisan db:seed --class=MasterUserSeeder
     */
    public function run(): void
    {
        // Cari file CSV di folder database/seeders/csv/
        $path = database_path('seeders/csv/users_master.csv');
 
        if (!file_exists($path)) {
            $this->command->error("Gagal: File CSV tidak ditemukan di $path");
            $this->command->warn("Silakan download CSV dari web dan letakkan di folder tersebut dengan nama 'users_master.csv'");
            return;
        }
 
        $file = fopen($path, 'r');
        $header = fgetcsv($file);
 
        if (!$header) {
            $this->command->error("Gagal: File CSV kosong atau format salah.");
            return;
        }
 
        $this->command->info("Memulai import user dari CSV...");
 
        DB::beginTransaction();
        try {
            $count = 0;
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($header, $row);
 
                // Resolve Warehouse ID via Warehouse Code (lebih robust)
                $warehouseId = null;
                if (!empty($data['warehouse_code'])) {
                    $warehouseId = \App\Models\Warehouse::where('warehouse_code', $data['warehouse_code'])->value('id');
                }
 
                // Fallback ke ID lama kalau code gak ketemu (backward compatibility)
                if (!$warehouseId && !empty($data['warehouse_id'])) {
                    $warehouseId = $data['warehouse_id'];
                }
 
                // Mapping kolom sesuai database lo
                $user = User::updateOrCreate(
                    ['username' => $data['username']], // Unik berdasarkan username
                    [
                        'name'           => $data['name'],
                        'email'          => $data['email'],
                        'phone'          => $data['phone'] ?? null,
                        'position'       => $data['position'] ?? null,
                        'signature_path' => $data['signature_path'] ?? null,
                        'password'       => $data['password'], // Pake Hash yang udah ada di CSV
                        'warehouse_id'   => $warehouseId,
                        'status'         => $data['status'] ?? 'active',
                    ]
                );
 
                // Pasang Role-nya (Berupa comma separated slug)
                if (!empty($data['role_slugs'])) {
                    $slugs = explode(',', $data['role_slugs']);
                    $roleIds = Role::whereIn('slug', $slugs)->pluck('id')->all();
                    $user->roles()->sync($roleIds);
                }
 
                $count++;
            }
 
            DB::commit();
            $this->command->info("Berhasil mengimpor $count user master!");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Terjadi kesalahan saat import: " . $e->getMessage());
        }
 
        fclose($file);
    }
}
