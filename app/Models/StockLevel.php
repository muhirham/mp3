<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    protected $table = 'stock_levels';

    protected $fillable = [
        'owner_type',   // 'pusat'|'warehouse'|'sales'
        'owner_id',     // id warehouse / pusat / sales
        'product_id',
        'quantity',     // saldo saat ini (atau saldo akumulasi)
    ];

    protected $casts = [
        'owner_id'  => 'int',
        'product_id'=> 'int',
        'quantity'  => 'int',
    ];

    // ===== Relasi =====
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Opsional: referensi warehouse berdasarkan owner
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'owner_id');
    }

    // ===== Scope bantu =====
    public function scopeForWarehouse($q, $warehouseId)
    {
        return $q->where('owner_type', 'warehouse')
                 ->when($warehouseId, fn($qq)=>$qq->where('owner_id', $warehouseId));
    }

    public function scopeForOwner($q, string $type, int $id)
    {
        return $q->where('owner_type', $type)->where('owner_id', $id);
    }
}