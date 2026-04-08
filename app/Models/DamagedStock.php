<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'source_type',
        'source_id',
        'quantity',
        'condition',
        'action',
        'status',
        'notes',
        'requested_by',
        'approved_by',
        'resolved_by',
        'approved_at',
        'resolved_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function photos()
    {
        return $this->hasMany(DamagedStockPhoto::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
