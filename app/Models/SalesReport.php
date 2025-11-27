<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReport extends Model
{
    protected $fillable = [
        'sales_id', 'warehouse_id', 'date',
        'total_sold', 'total_revenue', 'stock_remaining',
        'damaged_goods', 'goods_returned',
        'notes', 'status', 'approved_by', 'approved_at'
    ];

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
