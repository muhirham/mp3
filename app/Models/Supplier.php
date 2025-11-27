<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'supplier_code', 'name', 'address', 'phone', 'note', 'bank_name', 'bank_account' // Menambahkan bank_name dan bank_account
    ];

    public function restocks()
    {
        return $this->hasMany(RequestRestock::class);
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }


}
