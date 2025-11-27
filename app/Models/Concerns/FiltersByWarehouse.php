<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait FiltersByWarehouse
{
    /**
     * Scope untuk filter data berdasarkan warehouse_id.
     * Bisa dipakai di query: Model::forWarehouse($warehouseId)->get();
     */
    public function scopeForWarehouse(Builder $query, $warehouseId): Builder
    {
        if (empty($warehouseId)) {
            return $query;
        }

        // Asumsi model punya kolom warehouse_id
        return $query->where('warehouse_id', $warehouseId);
    }
}
