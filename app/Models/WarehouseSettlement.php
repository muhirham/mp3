<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'admin_id',
        'settlement_date',
        'total_cash_amount',
        'total_transfer_amount',
        'proof_path',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'total_cash_amount' => 'integer',
        'total_transfer_amount' => 'integer',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function handovers()
    {
        return $this->hasMany(SalesHandover::class, 'settlement_id');
    }
}
