<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrDeleteRequest extends Model
{
    protected $table = 'gr_delete_requests';

    protected $fillable = [
        'restock_receipt_id',
        'purchase_order_id',
        'requested_by',
        'approved_by',
        'status',
        'reason',
        'approval_note',
    ];

    protected $casts = [
        'restock_receipt_id' => 'int',
        'purchase_order_id'  => 'int',
        'requested_by'       => 'int',
        'approved_by'        => 'int',
    ];

    public function restockReceipt()
    {
        return $this->belongsTo(RestockReceipt::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
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
