<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesHandover extends Model
{
    use HasFactory;

    protected $table = 'sales_handovers';

    protected $fillable = [
        'code',
        'warehouse_id',
        'sales_id',
        'handover_date',
        'status',
        'issued_by',
        'reconciled_by',
        'reconciled_at',
        'total_dispatched_amount',
        'total_sold_amount',
        'morning_otp_hash',
        'morning_otp_expires_at',
        'closing_otp_hash',
        'closing_otp_expires_at',
        'cash_amount',
        'transfer_amount',
        'transfer_proof_path',
    ];

    protected $casts = [
        'handover_date'          => 'date',
        'reconciled_at'          => 'datetime',
        'morning_otp_expires_at' => 'datetime',
        'closing_otp_expires_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SalesHandoverItem::class, 'handover_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function reconciler()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
