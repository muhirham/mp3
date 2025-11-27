<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockSnapshot extends Model
{
    protected $fillable = [
        'owner_type', 'owner_id', 'product_id', 'quantity', 'recorded_at'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}