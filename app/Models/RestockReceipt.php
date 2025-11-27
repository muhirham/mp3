<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockReceipt extends Model
{
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
        return $this->belongsTo(\App\Models\PurchaseOrder::class, 'purchase_order_id');
    }

    public function request()
    {
        return $this->belongsTo(\App\Models\RequestRestock::class, 'request_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class, 'supplier_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function receiver()
    {
        return $this->belongsTo(\App\Models\User::class, 'received_by');
    }

    public function photos()
    {
        return $this->hasMany(\App\Models\RestockReceiptPhoto::class, 'receipt_id');
    }
    public function grDeleteRequests()
{
    return $this->hasMany(GrDeleteRequest::class);
}

}
