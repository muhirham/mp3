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
        'qty_start',
        'qty_returned',
        'qty_sold',
        'unit_price',
        'line_total_start',
        'line_total_sold',

        // ===== DISKON =====
        'discount_per_unit',
        'discount_total',
        'unit_price_after_discount',
        'line_total_after_discount',

        // payment per item
        'payment_qty',
        'payment_method',
        'payment_amount',
        'payment_transfer_proof_path',
        'payment_status',
        'payment_reject_reason',
    ];

    protected $casts = [
        'qty_start'          => 'integer',
        'qty_returned'       => 'integer',
        'qty_sold'           => 'integer',
        'unit_price'         => 'integer',
        'line_total_start'   => 'integer',
        'line_total_sold'    => 'integer',
        'discount_per_unit'          => 'integer',
        'discount_total'             => 'integer',
        'unit_price_after_discount'  => 'integer',
        'line_total_after_discount'  => 'integer',
        'payment_qty'        => 'integer',
        'payment_amount'     => 'integer',
        
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
