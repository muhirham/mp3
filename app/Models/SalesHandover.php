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
    'closed_by',

    // ===== TOTAL =====
    'total_dispatched_amount',
    'total_sold_amount',

    // ===== DISKON =====
    'total_discount',
    'total_sold_after_discount',

    'evening_filled_by_sales',
    'evening_filled_at',
    'cash_amount',
    'transfer_amount',
    'transfer_proof_path',

    'morning_otp_hash',
    'morning_otp_sent_at',
    'morning_otp_verified_at',
    'evening_otp_hash',
    'evening_otp_sent_at',
    'evening_otp_verified_at',
];


    protected $casts = [
    'handover_date' => 'date',

    'evening_filled_by_sales' => 'boolean',
    'evening_filled_at'       => 'datetime',

    'morning_otp_sent_at'     => 'datetime',
    'morning_otp_verified_at' => 'datetime',
    'evening_otp_sent_at'     => 'datetime',
    'evening_otp_verified_at' => 'datetime',

    // ===== TOTAL =====
    'total_dispatched_amount'    => 'integer',
    'total_sold_amount'          => 'integer',

    // ===== DISKON =====
    'discount_total'             => 'integer',
    'grand_total'  => 'integer',

    'cash_amount'     => 'integer',
    'transfer_amount' => 'integer',
];

    // ========= RELATIONSHIPS =========

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items()
    {
        return $this->hasMany(SalesHandoverItem::class, 'handover_id');
    }

    protected function extractPlainOtp(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_contains($value, '|')) {
            [$plain] = explode('|', $value, 2);
            $plain = trim($plain);
            return $plain !== '' ? $plain : null;
        }

        // data lama: cuma hash, plain-nya nggak bisa diambil
        return null;
    }

    public function getMorningOtpPlainAttribute(): ?string
    {
        return $this->extractPlainOtp($this->morning_otp_hash);
    }

    public function getEveningOtpPlainAttribute(): ?string
    {
        return $this->extractPlainOtp($this->evening_otp_hash);
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class, 'handover_id');
    }

}
