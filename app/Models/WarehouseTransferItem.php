<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WarehouseTransferItem extends Model
{
    use HasFactory;

    protected $table = 'warehouse_transfer_items';

    protected $fillable = [
        'warehouse_transfer_id',
        'product_id',
        'qty_transfer',
        'qty_good',
        'qty_damaged',
        'unit_cost',
        'subtotal_cost',
        'note',
        'photo_good',
        'photo_damaged',
    ];


    protected $casts = [
        'quantity'      => 'integer',
        'unit_cost'     => 'decimal:2',
        'subtotal_cost' => 'decimal:2',
    ];

    /* ================= RELATION ================= */

    public function transfer()
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /* ================= EVENT ================= */

    protected static function booted()
    {
        static::saving(function ($item) {
            $item->subtotal_cost = $item->quantity * $item->unit_cost;
        });
    }
}
