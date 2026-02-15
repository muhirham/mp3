<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BomTransaction extends Model
{
    use HasFactory;
    protected $table = 'bom_transactions';

    protected $fillable = [
        'bom_id',
        'product_id',       // finished product
        'production_qty',
        'total_cost',
        'user_id',
    ];

    protected $casts = [
        'production_qty' => 'integer',
        'total_cost'     => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    // finished product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(BomTransactionItem::class);
    }

}
