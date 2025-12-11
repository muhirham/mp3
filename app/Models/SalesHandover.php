<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

        // amount
        'total_dispatched_amount',
        'total_sold_amount',
        'cash_amount',
        'transfer_amount',
        'transfer_proof_path',

        // OTP
        'issue_otp_hash',
        'issue_otp_expires_at',
        'otp_hash',
        'otp_expires_at',
    ];

    protected $casts = [
        'handover_date'           => 'date',
        'issue_otp_expires_at'    => 'datetime',
        'otp_expires_at'          => 'datetime',
        'total_dispatched_amount' => 'decimal:2',
        'total_sold_amount'       => 'decimal:2',
        'cash_amount'             => 'decimal:2',
        'transfer_amount'         => 'decimal:2',
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
