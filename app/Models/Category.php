<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'category_code', 'category_name', 'description',
    ];

    /**
     * Generate kode kategori berikutnya, misal: CAT-001, CAT-002, dst.
     */
    public static function nextCode(): string
    {
        $nextId = (static::max('id') ?? 0) + 1;

        return 'CAT-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }
}
