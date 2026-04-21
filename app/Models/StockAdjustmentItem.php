<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'qty_before',
        'qty_after',
        'qty_diff',
        'purchase_price_before',
        'purchase_price_after',
        'selling_price_before',
        'selling_price_after',
        'notes',
    ];
 
    protected $casts = [
        'qty_before'            => 'integer',
        'qty_after'             => 'integer',
        'qty_diff'              => 'integer',
        'purchase_price_before' => 'integer',
        'purchase_price_after'  => 'integer',
        'selling_price_before'  => 'integer',
        'selling_price_after'   => 'integer',
    ];

    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
