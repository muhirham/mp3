<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_code',
        'name',
        'category_id',
        'product_type',
        'description',
        'purchasing_price',
        'standard_cost',
        'selling_price',
        'stock_minimum',
        'supplier_id',
        'package_id',
        'is_active',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockRequests()
    {
        return $this->hasMany(StockRequest::class);
    }

    public function restocks()
    {
        return $this->hasMany(RequestRestock::class);
    }

        public function assemblySaldoUsages()
    {
        return $this->hasMany(AssemblyTransaction::class, 'saldo_id');
    }

    public function assemblyKpkUsages()
    {
        return $this->hasMany(AssemblyTransaction::class, 'kpk_id');
    }

        public function isMaterial()
    {
        return $this->product_type === 'material';
    }

    public function isFinished()
    {
        return $this->product_type === 'finished';
    }

    public function isNormal()
    {
        return $this->product_type === 'normal';
    }
    
}