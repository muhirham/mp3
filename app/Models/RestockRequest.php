<?php

namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Factories\HasFactory;

    class RestockRequest extends Model
    {
        use HasFactory;

        protected $table = 'restock_requests';

        protected $fillable = [
            'product_id',
            'supplier_id',
            'warehouse_id',
            'user_id',
            'request_date',
            'quantity_requested',
            'total_cost',
            'description',
            'status',            // pending | approved | rejected
        ];

        protected $casts = [
            'request_date'        => 'date',
            'quantity_requested'  => 'float',
            'total_cost'          => 'float',
        ];

        // biar ikut ke JSON
        protected $appends = ['unit_price'];

        # -------------------- Relationships --------------------
        public function product()
        {
            return $this->belongsTo(Product::class, 'product_id'); // sesuaikan kalau model kamu beda
        }

        public function supplier()
        {
            return $this->belongsTo(Supplier::class, 'supplier_id'); // sesuaikan kalau model kamu beda
        }

        public function warehouse()
        {
            return $this->belongsTo(Warehouse::class, 'warehouse_id'); // sesuaikan kalau model kamu beda
        }

        public function requester()
        {
            return $this->belongsTo(User::class, 'user_id');
        }

        # -------------------- Defaults / Hooks --------------------
        protected static function booted()
        {
            static::creating(function ($m) {
                if (empty($m->status))       $m->status = 'pending';
                if (empty($m->request_date)) $m->request_date = now();
            });
        }

        # -------------------- Accessors --------------------
        // Harga satuan dihitung dari total_cost / qty (fallback 0 kalau qty 0)
        public function getUnitPriceAttribute(): float
        {
            $qty = (float) ($this->quantity_requested ?? 0);
            $total = (float) ($this->total_cost ?? 0);
            return $qty > 0 ? round($total / $qty, 2) : 0.0;
        }

        public function getStatusLabelAttribute(): string
        {
            return ucfirst($this->status ?? 'pending');
        }

        # -------------------- Query Scopes --------------------
        public function scopeStatus($q, ?string $status)
        {
            if ($status) $q->where('status', $status);
            return $q;
        }

        public function scopeForSupplier($q, $supplierId)
        {
            if ($supplierId) $q->where('supplier_id', $supplierId);
            return $q;
        }

        public function scopeForWarehouse($q, $warehouseId)
        {
            if ($warehouseId) $q->where('warehouse_id', $warehouseId);
            return $q;
        }

        public function scopeForProduct($q, $productId)
        {
            if ($productId) $q->where('product_id', $productId);
            return $q;
        }

        public function scopeDateBetween($q, $from = null, $to = null)
        {
            if ($from) $q->whereDate('request_date', '>=', $from);
            if ($to)   $q->whereDate('request_date', '<=', $to);
            return $q;
        }

        public function scopeQuickSearch($q, ?string $term)
        {
            if (!$term) return $q;

            return $q->where(function ($w) use ($term) {
                $w->where('description', 'like', "%{$term}%")
                ->orWhereHas('product',   fn($qq) => $qq->where('product_name', 'like', "%{$term}%"))
                ->orWhereHas('supplier',  fn($qq) => $qq->where('name', 'like', "%{$term}%")
                                                        ->orWhere('company_name', 'like', "%{$term}%"))
                ->orWhereHas('warehouse', fn($qq) => $qq->where('warehouse_name', 'like', "%{$term}%"));
            });
        }
    }
