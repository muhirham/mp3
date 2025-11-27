<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $fillable = [
        'sales_id', 'warehouse_id', 'product_id',
        'quantity', 'condition', 'reason',
        'status', 'approved_by', 'approved_at'
    ];

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
