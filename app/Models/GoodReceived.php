<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodReceived extends Model
{
    // Pakai table restock_receipts yang sudah ada
    protected $table = 'restock_receipts';

    protected $fillable = [
        'purchase_order_id',
        'request_id',
        'warehouse_id',
        'supplier_id',
        'product_id',
        'code',
        'qty_requested',
        'qty_good',
        'qty_damaged',
        'cost_per_item',
        'notes',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'qty_requested' => 'int',
        'qty_good'      => 'int',
        'qty_damaged'   => 'int',
        'cost_per_item' => 'int',
        'received_at'   => 'datetime',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(\App\Models\PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function receiver()
    {
        return $this->belongsTo(\App\Models\User::class, 'received_by');
    }

    public function photos()
    {
        return $this->hasMany(GoodReceivedPhoto::class, 'receipt_id');
    }
}
