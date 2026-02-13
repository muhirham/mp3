<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyResult extends Model
{
    protected $fillable = [
        'name',
        'qty',
    ];

    public function transactions()
    {
        return $this->hasMany(AssemblyTransaction::class);
    }
}
