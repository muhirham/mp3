<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\FiltersByWarehouse;

class RequestRestock extends Model
{
    use FiltersByWarehouse;

    protected $table = 'request_restocks';

    protected $fillable = [
        'code',                // <-- tambahin ini
        'supplier_id',
        'product_id',
        'warehouse_id',
        'requested_by',
        'quantity_requested',
        'quantity_received',
        'cost_per_item',
        'total_cost',
        'status',
        'note',
        'approved_by',
        'approved_at',
        'received_at',
    ];

    protected $casts = [
        'quantity_requested' => 'int',
        'quantity_received'  => 'int',
        'cost_per_item'      => 'int',
        'total_cost'         => 'int',
        'approved_at'        => 'datetime',
        'received_at'        => 'datetime',
    ];

    // Relasi...
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
