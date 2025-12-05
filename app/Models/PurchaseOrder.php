<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\RestockReceipt;
use App\Models\Warehouse;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_code',
        'supplier_id',
        'ordered_by',
        'status',
        'subtotal',
        'discount_total',
        'grand_total',
        'notes',           // dipakai juga buat alasan reject
        'ordered_at',

        'approval_status',
        'approved_by_procurement',
        'approved_by_ceo',
        'approved_at_procurement',
        'approved_at_ceo',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
    ];

    // ===== RELATION =====

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // creator PO
    public function user()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function restockReceipts()
    {
        return $this->hasMany(RestockReceipt::class, 'purchase_order_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function procurementApprover()
    {
        return $this->belongsTo(User::class, 'approved_by_procurement');
    }

    public function ceoApprover()
    {
        return $this->belongsTo(User::class, 'approved_by_ceo');
    }

    // ===== LOGIC =====

    // Hitung ulang total berdasarkan items
    public function recalcTotals(): void
    {
        $sub   = 0;
        $disc  = 0;
        $grand = 0;

        foreach ($this->items as $it) {
            $lineSub  = $it->qty_ordered * $it->unit_price;
            $lineDisc = 0;

            if ($it->discount_type === 'percent') {
                $lineDisc = $lineSub * ((float) $it->discount_value / 100);
            } elseif ($it->discount_type === 'amount') {
                $lineDisc = (float) $it->discount_value;
            }

            $lineTot        = max(0, $lineSub - $lineDisc);
            $it->line_total = $lineTot;
            $it->save();

            $sub   += $lineSub;
            $disc  += $lineDisc;
            $grand += $lineTot;
        }

        $this->subtotal       = $sub;
        $this->discount_total = $disc;
        $this->grand_total    = $grand;
        $this->save();
    }

    public function derivedStatus(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        if ($this->items->every(fn ($i) => $i->qty_received >= $i->qty_ordered && $i->qty_ordered > 0)) {
            return 'completed';
        }

        if ($this->items->contains(fn ($i) => $i->qty_received > 0)) {
            return 'partially_received';
        }

        return $this->status;
    }

    public function isInApprovalFlow(): bool
    {
        return in_array($this->approval_status, ['waiting_procurement', 'waiting_ceo'], true);
    }

    public function isFullyApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }
}
