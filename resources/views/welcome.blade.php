    <!DOCTYPE html>
    <html lang="en" class="light-style layout-menu-fixed" dir="ltr"
        data-theme="theme-default" data-assets-path="{{ asset('sneat/assets/') }}"
        data-template="vertical-menu-template-free">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>
        <title>Dashboard - User</title>

        <link rel="icon" type="image/x-icon" href="{{ asset('sneat/assets/img/favicon/favicon.ico') }}" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>

        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/fonts/boxicons.css') }}" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/core.css') }}" class="template-customizer-core-css" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/css/demo.css') }}" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/libs/apex-charts/apex-charts.css') }}" />

                <style>
            /* ========= THEME TOKENS ========= */
            :root{
                /* Light */
                --bg-body:#f5f7fb;
                --bg-card:#ffffff;
                --text:#1f2937;
                --muted:#6b7280;
                --border:#e5e7eb;
                --menu-bg:#ffffff;
                --navbar-bg:#ffffff;
                --footer-bg:#ffffff;
                --input-bg:#ffffff;
            }
            html[data-color-scheme="dark"]{
                /* Dark */
                --bg-body:#121625;
                --bg-card:#1a2035;
                --text:#e5e7eb;
                --muted:#a1a1aa;
                --border:#2b3248;
                --menu-bg:#1a2035;
                --navbar-bg:#1a2035;
                --footer-bg:#1a2035;
                --input-bg:#141a2b;
            }

            /* ========= GLOBAL APPLY ========= */
            body{ background:var(--bg-body); color:var(--text); }

            .layout-menu.menu{ background:var(--menu-bg)!important; }
            .bg-navbar-theme{ background:var(--navbar-bg)!important; }
            .footer.bg-footer-theme{ background:var(--footer-bg)!important; }

            .card,
            .modal-content,
            .dropdown-menu,
            .offcanvas,
            .list-group,
            .toast{
                background:var(--bg-card);
                color:var(--text);
                border-color:var(--border);
            }

            .text-muted{ color:var(--muted)!important; }

            /* Border harmonized */
            .card,
            .modal-content,
            .dropdown-menu,
            .offcanvas,
            .table,
            .form-control,
            .form-select,
            .input-group-text{
                border-color:var(--border)!important;
            }

            /* Tables */
            .table{ color:var(--text); }
            .table thead th{ border-bottom-color:var(--border)!important; }
            .table td,.table th{ border-top-color:var(--border)!important; }

            /* ========= FORM ELEMENTS ========= */
            .form-control,
            .form-select{
                background:var(--input-bg)!important;
                color:var(--text)!important;
                border-color:var(--border)!important;
                caret-color:var(--text)!important;
            }
            .form-control::placeholder{ color:var(--muted)!important; opacity:1; }

            /* Focus ring (di kedua tema) */
            .form-control:focus,
            .form-select:focus{
                border-color:#6366f1!important; /* indigo soft */
                box-shadow:0 0 0 .25rem rgba(99,102,241,.25)!important;
            }

            /* Dropdown option (sebatas support browser) */
            html[data-color-scheme="dark"] .form-select{
                color:var(--text)!important;
                background:var(--input-bg)!important;
            }
            html[data-color-scheme="dark"] .form-select option{
                color:var(--text)!important;
                background:var(--bg-card)!important;
            }

            /* Autofill Chrome (hindari kuning) */
            input:-webkit-autofill{
                -webkit-text-fill-color:var(--text)!important;
                box-shadow:0 0 0 1000px var(--input-bg) inset!important;
                transition:background-color 9999s ease-in-out 0s;
            }
            </style>


        <script src="{{ asset('sneat/assets/vendor/js/helpers.js') }}"></script>
        <script src="{{ asset('sneat/assets/js/config.js') }}"></script>

        {{-- SweetAlert2 --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


        <script>
        (function(){
            const KEY='theme-preference';
            const pref=localStorage.getItem(KEY)||'system';
            const isDark = pref==='dark' || (pref==='system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-color-scheme', isDark ? 'dark' : 'light');
        })();
        (function(){
            const KEY='theme-preference'; const media=window.matchMedia('(prefers-color-scheme: dark)');
            function resolved(x){return x==='system'?(media.matches?'dark':'light'):x}
            function apply(x){
            const m = resolved(x); document.documentElement.setAttribute('data-color-scheme', m);
            const ico = document.getElementById('themeIcon'); if(ico){ico.classList.remove('bx-sun','bx-moon'); ico.classList.add(m==='dark'?'bx-moon':'bx-sun');}
            if(window.Apex){window.Apex.theme={mode:m}}
            }
            window.setTheme = function(x){localStorage.setItem(KEY,x); apply(x)}
            apply(localStorage.getItem(KEY)||'system');
            media.addEventListener('change', ()=>{ if((localStorage.getItem(KEY)||'system')==='system') apply('system')});
        })();
        </script>

        @stack('styles')
    </head>
    <body>
        <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            {{-- Sidebar --}}
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
            <div class="app-brand demo">
                <a href="#" class="app-brand-link"><span class="app-brand-text demo menu-text fw-bolder ms-2">USERS</span></a>
                <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                <i class="bx bx-chevron-left bx-sm align-middle"></i>
                </a>
            </div>

            <div class="menu-inner-shadow"></div>
            <ul class="menu-inner py-1">
                <li class="menu-item {{ request()->routeIs('welcome') ? 'active' : '' }}">
                <a href="{{ route('welcome') }}" class="menu-link"><i class="menu-icon tf-icons bx bx-home-circle"></i><div>Dashboard</div></a>
                </li>
                <li class="menu-item {{ request()->routeIs('requestForm*') ? 'active' : '' }}">
                <a href="{{ route('requestForm.index') }}" class="menu-link"><i class="menu-icon tf-icons bx bx-collection"></i><div>Request Form</div></a>
                </li>
            </ul>
            </aside>

            <div class="layout-page">
            <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                </div>

                <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                <div class="navbar-nav align-items-center">
                    <div class="nav-item d-flex align-items-center">
                    <i class="bx bx-search fs-4 lh-0"></i>
                    <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..."/>
                    </div>
                </div>

                <ul class="navbar-nav flex-row align-items-center ms-auto">
                    <li class="nav-item dropdown me-3">
                    <a class="nav-link dropdown-toggle hide-arrow" href="#" data-bs-toggle="dropdown" aria-expanded="false" title="Theme">
                        <i id="themeIcon" class="bx bx-moon"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-1" style="min-width:10rem">
                        <li><a id="theme-light"  class="dropdown-item" href="#" onclick="setTheme('light')"><i class="bx bx-sun me-2"></i>Light</a></li>
                        <li><a id="theme-dark"   class="dropdown-item" href="#" onclick="setTheme('dark')"><i class="bx bx-moon me-2"></i>Dark</a></li>
                        <li><a id="theme-system" class="dropdown-item" href="#" onclick="setTheme('system')"><i class="bx bx-desktop me-2"></i>System</a></li>
                    </ul>
                    </li>

                                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                    <img src="{{ asset('sneat/assets/img/avatars/1.png') }}" alt class="w-px-40 h-auto rounded-circle" />
                    </div>
                </a>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                    <a class="dropdown-item" href="#">
                        <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-online">
                            <img src="{{ asset('sneat/assets/img/avatars/1.png') }}" class="w-px-40 h-auto rounded-circle" />
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <span class="fw-semibold d-block">{{ auth()->user()->name ?? 'User' }}</span>
                            <small class="text-muted">{{ auth()->user()->role ?? 'Member' }}</small>
                        </div>
                        </div>
                    </a>
                    </li>

                    <li><div class="dropdown-divider"></div></li>

                    <li>
                    <a class="dropdown-item" href="#">
                        <i class="bx bx-user me-2"></i>
                        <span class="align-middle">My Profile</span>
                    </a>
                    </li>

                    <li>
                    <a class="dropdown-item" href="#">
                        <i class="bx bx-cog me-2"></i>
                        <span class="align-middle">Settings</span>
                    </a>
                    </li>

                    <li><div class="dropdown-divider"></div></li>

                    {{-- Tombol Logout --}}
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

            <div class="content-wrapper">
                @yield('content')
            </div>

            <footer class="content-footer footer bg-footer-theme">
                <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                    © <script>document.write(new Date().getFullYear());</script>, made with ❤️ by
                    <a href="https://themeselection.com" target="_blank" class="footer-link fw-bolder">ThemeSelection</a>
                </div>
                <div>
                    <a href="https://themeselection.com/license/" class="footer-link me-4" target="_blank">License</a>
                    <a href="https://themeselection.com/" target="_blank" class="footer-link me-4">More Themes</a>
                    <a href="https://themeselection.com/demo/sneat-bootstrap-html-admin-template/documentation/" target="_blank" class="footer-link me-4">Documentation</a>
                    <a href="https://github.com/themeselection/sneat-html-admin-template-free/issues" target="_blank" class="footer-link me-4">Support</a>
                </div>
                </div>
            </footer>
            <div class="content-backdrop fade"></div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
        </div>

        {{-- Core JS --}}
        <script src="{{ asset('sneat/assets/vendor/libs/jquery/jquery.js') }}"></script>
        <script>window.jQuery||document.write('<script src="https://code.jquery.com/jquery-3.6.4.min.js"><\/script>')</script>
        <script src="{{ asset('sneat/assets/vendor/libs/popper/popper.js') }}"></script>
        <script src="{{ asset('sneat/assets/vendor/js/bootstrap.js') }}"></script>
        <script src="{{ asset('sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
        <script src="{{ asset('sneat/assets/vendor/js/menu.js') }}"></script>

        {{-- tempatkan script halaman (mis. DataTables) --}}
        @stack('scripts')

        <script src="{{ asset('sneat/assets/vendor/libs/apex-charts/apex-charts.js') }}"></script>
        <script src="{{ asset('sneat/assets/js/main.js') }}"></script>
    </body>
    </html>
