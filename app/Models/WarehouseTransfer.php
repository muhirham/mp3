<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WarehouseTransfer extends Model
{
    use HasFactory;

    protected $table = 'warehouse_transfers';

    protected $fillable = [
        'transfer_code',
        'source_warehouse_id',
        'destination_warehouse_id',
        'status',
        'created_by',
        'approved_source_by',
        'approved_destination_by',
        'approved_source_at',
        'approved_destination_at',
        'total_cost',
        'note',
    ];

    protected $casts = [
        'approved_source_at'       => 'datetime',
        'approved_destination_at'  => 'datetime',
        'total_cost'               => 'decimal:2',
    ];

    /* ================= RELATION ================= */

    public function sourceWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedSourceBy()
    {
        return $this->belongsTo(User::class, 'approved_source_by');
    }

    public function approvedDestinationBy()
    {
        return $this->belongsTo(User::class, 'approved_destination_by');
    }

    public function items()
    {
        return $this->hasMany(WarehouseTransferItem::class);
    }

    public function logs()
    {
        return $this->hasMany(WarehouseTransferLog::class);
    }

    /* ================= HELPER ================= */

    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isPendingSource()
    {
        return $this->status === 'pending_source';
    }

    public function isPendingDestination()
    {
        return $this->status === 'pending_destination';
    }

    public function isApproved()
    {
        return in_array($this->status, ['approved', 'source_gr']);
    }
    public function isCanceled()
    {
        return $this->status === 'canceled';
    }
    public function isSourceGr()
    {
        return $this->status === 'source_gr';
    }
    public function isCompleted()
    {
        return $this->status === 'completed';
    }
}
