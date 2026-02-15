<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'bom_id',
        'material_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
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

    // Material product
    public function material()
    {
        return $this->belongsTo(Product::class, 'material_id');
    }
}
