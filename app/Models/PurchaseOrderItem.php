<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    // PENTING: request_id, product_id, warehouse_id, dst harus boleh diisi
    protected $guarded = []; // atau pakai $fillable sesuai kebutuhan

    public function po()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function request()
    {
        return $this->belongsTo(RequestRestock::class, 'request_id');
    }
}
