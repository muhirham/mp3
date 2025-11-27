@php
use Illuminate\Support\Facades\Route as R;

$u    = auth()->user();
$role = $u?->primaryRole()?->slug ?? 'guest';

// helper route aman
$rl = function (string $name, array $params = []) {
    return R::has($name) ? route($name, $params) : '#';
};

// config menu
$groupsConfig = config('menu.groups');
$items        = collect(config('menu.items'));

// keys yang boleh (dari role)
$allowed = $u ? $u->allowedMenuKeys() : [];

// filter item sesuai allowed
$visibleItems = $items->filter(fn ($it) => in_array($it['key'], $allowed, true));

// group by 'group' => inventory, warehouse, dll
$grouped = $visibleItems->groupBy('group');

// route dashboard sesuai role
$dashboardRoute = match ($role) {
    'admin'     => 'admin.dashboard',
    'warehouse' => 'warehouse.dashboard',
    'sales'     => 'sales.dashboard',
    default     => 'login',
};

// helper: cek apakah group sedang aktif (ada child yg route-nya aktif)
$isGroupActive = function ($groupKey, $list) {
    return $list->contains(function ($it) {
        return request()->routeIs(($it['route'] ?? '').'*');
    });
};
@endphp

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ $rl($dashboardRoute) }}" class="app-brand-link">
            <span class="app-brand-text demo menu-text fw-bolder ms-2">{{ ucfirst($role) }}</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        {{-- Dashboard (selalu ada) --}}
        <li class="menu-item {{ request()->routeIs($dashboardRoute) ? 'active' : '' }}">
            <a href="{{ $rl($dashboardRoute) }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div>Dashboard</div>
            </a>
        </li>

        {{-- Loop per GROUP sebagai DROPDOWN --}}
        @foreach($grouped as $gKey => $list)
            @php
                $meta      = $groupsConfig[$gKey] ?? ['label' => ucfirst($gKey), 'icon' => 'bx bx-folder'];
                $parentLbl = $meta['label'] ?? $meta;
                $parentIco = $meta['icon']  ?? 'bx bx-folder';
                $open      = $isGroupActive($gKey, $list);
            @endphp

            <li class="menu-item {{ $open ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons {{ $parentIco }}"></i>
                    <div>{{ $parentLbl }}</div>
                </a>

                <ul class="menu-sub">
                    @foreach($list as $it)
                        <li class="menu-item {{ request()->routeIs($it['route'].'*') ? 'active' : '' }}">
                            <a href="{{ $rl($it['route']) }}" class="menu-link">
                                <div>{{ $it['label'] }}</div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>
</aside>
