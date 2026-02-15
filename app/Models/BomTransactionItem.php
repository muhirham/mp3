<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BomTransactionItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'bom_transaction_id',
        'material_id',
        'qty_used',
        'cost_per_unit',
        'total_cost',
    ];

    protected $casts = [
        'qty_used' => 'float',
        'cost_per_unit' => 'float',
        'total_cost' => 'float',
    ];

    public function transaction()
    {
        return $this->belongsTo(BomTransaction::class, 'bom_transaction_id');
    }

    public function material()
    {
        return $this->belongsTo(Product::class, 'material_id');
    }
}

