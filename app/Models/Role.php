<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name','slug','home_route','menu_keys'];

    protected $casts = [
        'menu_keys' => 'array',
    ];

    public function users()
    {
        // sama2 pakai role_user (role_id, user_id)
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
                   
    }
}
