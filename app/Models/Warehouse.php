<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'warehouse_code', 'warehouse_name', 'address', 'note'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class, 'owner_id');
    }

    public function salesReports()
    {
        return $this->hasMany(SalesReport::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Generate kode warehouse berikutnya, misal: WH-001, WH-002, dst.
     * Basisnya pakai max(id) supaya aman walau kode lama campur-campur.
     */
    public static function nextCode(): string
    {
        $nextId = (static::max('id') ?? 0) + 1;

        return 'WH-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }
}
