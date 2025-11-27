<?php

namespace App\Models;
use App\Models\Concerns\FiltersByWarehouse;
use Illuminate\Database\Eloquent\Model;


class StockMovement extends Model
{
    use FiltersByWarehouse;

    protected $fillable = [
        'product_id', 'from_type', 'from_id',
        'to_type', 'to_id', 'quantity',
        'status', 'approved_by', 'approved_at', 'note'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}