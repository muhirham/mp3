<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyTransaction extends Model
{
        protected $fillable = [
        'saldo_id',
        'kpk_id',
        'qty',
        'saldo_per_unit',
        'saldo_used',
        'saldo_before',
        'saldo_after',
        'created_by',
    ];

    public function saldo()
    {
        return $this->belongsTo(Product::class, 'saldo_id');
    }

    public function kpk()
    {
        return $this->belongsTo(Product::class, 'kpk_id');
    }

    public function result()
    {
        return $this->belongsTo(AssemblyResult::class, 'assembly_result_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
