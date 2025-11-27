<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = ['package_name'];
    public function products() { return $this->hasMany(Product::class); }
}