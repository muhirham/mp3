<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockReceiptPhoto extends Model
{
    protected $table = 'restock_receipt_photos';

    protected $fillable = [
        'receipt_id',
        'path',
        'caption',
    ];

    public function receipt()
    {
        return $this->belongsTo(\App\Models\RestockReceipt::class, 'receipt_id');
    }
}
