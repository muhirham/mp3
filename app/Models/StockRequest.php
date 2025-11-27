<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequest extends Model
{
    protected $fillable = [
        'requester_type', 'requester_id',
        'approver_type', 'approver_id',
        'product_id', 'quantity_requested',
        'quantity_approved', 'status', 'note'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
