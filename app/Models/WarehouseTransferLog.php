<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WarehouseTransferLog extends Model
{
    use HasFactory;

    protected $table = 'warehouse_transfer_logs';

    protected $fillable = [
        'warehouse_transfer_id',
        'performed_by',
        'action',
        'note',
    ];

    /* ================= RELATION ================= */

    public function transfer()
    {
        return $this->belongsTo(WarehouseTransfer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
