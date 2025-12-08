<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesHandoverItem extends Model
{
    use HasFactory;

    protected $table = 'sales_handover_items';

    protected $fillable = [
        'handover_id',
        'product_id',
        'qty_dispatched',
        'qty_returned_good',
        'qty_returned_damaged',
        'qty_sold',
        'unit_price',
        'line_total_dispatched',
        'line_total_sold',
    ];

    protected $casts = [
        'unit_price'             => 'float',
        'line_total_dispatched'  => 'float',
        'line_total_sold'        => 'float',
    ];

    public function handover()
    {
        return $this->belongsTo(SalesHandover::class, 'handover_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
