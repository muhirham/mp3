<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesHandoverItem extends Model
{
    use HasFactory;

    protected $table = 'sales_handover_items';

    protected $fillable = [
        'handover_id',
        'product_id',
        'qty_start',
        'qty_returned',
        'qty_sold',
        'unit_price',
        'line_total_start',
        'line_total_sold',
    ];

    protected $casts = [
        'qty_start'        => 'integer',
        'qty_returned'     => 'integer',
        'qty_sold'         => 'integer',
        'unit_price'       => 'float',
        'line_total_start' => 'float',
        'line_total_sold'  => 'float',
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
