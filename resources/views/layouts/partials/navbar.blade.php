{{-- resources/views/layouts/partials/navbar.blade.php --}}
<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme">
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
  </div>

  <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <div class="navbar-nav align-items-center">
      <div class="nav-item d-flex align-items-center">
        <i class="bx bx-search fs-10 lh-10"></i>
        <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search...">
      </div>
    </div>

    <ul class="navbar-nav flex-row align-items-center ms-auto">
      <li class="nav-item dropdown-user dropdown">
        <a class="nav-link dropdown-toggle hide-arrow" href="#" data-bs-toggle="dropdown">
          <div class="avatar avatar-online">
            <img src="{{ asset('sneat/assets/img/avatars/1.png') }}" class="w-px-40 h-auto rounded-circle" alt="">
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li class="px-3 py-2">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('sneat/assets/img/avatars/1.png') }}" class="w-px-40 h-auto rounded-circle" alt="">
              </div>
              <div>
                <div class="fw-semibold">{{ auth()->user()->name ?? 'Guest' }}</div>
                <small class="text-muted">{{ ucfirst(auth()->user()->role ?? '-') }}</small>
              </div>
            </div>
          </li>
          <li><div class="dropdown-divider"></div></li>
          <li>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="dropdown-item">
                <i class="bx bx-power-off me-2"></i> Log Out
              </button>
            </form>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</nav>
