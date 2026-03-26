<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequest extends Model
{
    protected $fillable = [
        'user_id',
        'warehouse_id',
        'product_id',
        'quantity_requested',
        'quantity_approved',
        'status',
        'approved_by',
        'sales_handover_id',
        'note'
    ];

    /* ================= RELATION ================= */

    public function user()
    {
        return $this->belongsTo(User::class);
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

    public function salesHandover()
    {
        return $this->belongsTo(SalesHandover::class);
    }
}