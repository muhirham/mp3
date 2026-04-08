<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedStockPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'damaged_stock_id',
        'path',
        'kind'
    ];

    public function damagedStock()
    {
        return $this->belongsTo(DamagedStock::class);
    }
}
