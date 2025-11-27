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
        'password',
        'warehouse_id',
        'status',
        'role', // legacy (boleh dipakai fallback)
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts  = [
        'email_verified_at' => 'datetime',
    ];

    /* ================== RELASI ================== */

    // pivot roles (tabel role_user: user_id, role_id)
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    // relasi ke warehouse tempat user ditempatkan
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /* ================== HELPER ROLE ================== */

    // role utama (prioritas admin > warehouse > sales)
    public function primaryRole()
    {
        return $this->roles()
            ->select('slug','home_route')
            ->orderByRaw("FIELD(slug,'admin','warehouse','sales')")
            ->first();
    }

    // cek role (terima string/array)
    public function hasRole(string|array $slugs): bool
    {
        $need = is_array($slugs) ? $slugs : [$slugs];

        return $this->roles()->whereIn('slug', $need)->exists()
            || in_array(($this->role ?? ''), $need, true); // fallback kolom lama
    }

    /* ================== HELPER MENU ================== */

    // gabung semua menu_keys dari seluruh role
    public function allMenuKeys(): array
    {
        return $this->roles()
            ->pluck('menu_keys')   // koleksi: [[a,b],[c],null,...]
            ->filter()             // buang null
            ->flatten()            // jadi [a,b,c,...]
            ->unique()
            ->values()
            ->all();
    }

    // boleh lihat menu key tertentu?
    public function canSeeMenu(string $key): bool
    {
        return in_array($key, $this->allMenuKeys(), true);
    }

    // alias lama biar kode yang pakai allowedMenuKeys() tetap aman
    public function allowedMenuKeys(): array
    {
        return $this->allMenuKeys();
    }
}
