<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // Aman walau kolom supplier_id/warehouse_id belum ada—yang penting controller
    // hanya mengirim field yang memang exist di DB.
    protected $fillable = [
        'product_code', 'name', 'category_id', 'supplier_id', 'package_id',
        'description', 'purchasing_price', 'selling_price', 'stock_minimum'
    ];

    public function category()  { return $this->belongsTo(Category::class); }
    public function supplier()  { return $this->belongsTo(Supplier::class); }
    public function package()  { return $this->belongsTo(Package::class); }

    public function stockLevels()    { return $this->hasMany(StockLevel::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
    public function stockRequests()  { return $this->hasMany(StockRequest::class); }
    public function restocks()       { return $this->hasMany(RequestRestock::class); }
}