<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Role;
use App\Models\Warehouse;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'position',
        'signature_path',
        'password',
        'warehouse_id',
        'status',
        'role', // legacy
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /* ================== RELASI ================== */

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /* ================== HELPER ROLE ================== */

    public function primaryRole()
    {
        return $this->roles()
            ->select('slug', 'home_route')
            ->orderByRaw("FIELD(slug,'admin','warehouse','sales')")
            ->first();
    }

    public function hasRole(string|array $slugs): bool
    {
        $need = is_array($slugs) ? $slugs : [$slugs];

        return $this->roles()->whereIn('slug', $need)->exists()
            || in_array(($this->role ?? ''), $need, true); // fallback kolom lama
    }

    /* ================== HELPER MENU ================== */

    public function allMenuKeys(): array
    {
        return $this->roles()
            ->pluck('menu_keys')
            ->filter()
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    public function canSeeMenu(string $key): bool
    {
        return in_array($key, $this->allMenuKeys(), true);
    }

    public function allowedMenuKeys(): array
    {
        return $this->allMenuKeys();
    }
}
