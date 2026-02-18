<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'short_name',
        'code',
        'address',
        'city',
        'province',
        'phone',
        'email',
        'website',
        'tax_number',
        'logo_path',
        'logo_small_path',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

public function getLogoUrlAttribute()
{
    if (!$this->logo_path) {
        return null;
    }

    // Kalau file ada di public langsung
    if (file_exists(public_path($this->logo_path))) {
        return asset($this->logo_path);
    }

    // Kalau file ada di storage/app/public
    if (file_exists(storage_path('app/public/' . $this->logo_path))) {
        return asset('storage/' . $this->logo_path);
    }

    return null;
}


}
