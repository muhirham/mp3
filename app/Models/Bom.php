<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bom extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'bom_code',
        'version',
        'output_qty',
        'is_active',
        'created_by',
        'updated_by',
    ];


    protected $casts = [
        'output_qty' => 'integer',
        'is_active'  => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    // Finished product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Materials
    public function items()
    {
        return $this->hasMany(BomItem::class);
    }

    // Production history
    public function productions()
    {
        return $this->hasMany(BomTransaction::class);
    }
}
