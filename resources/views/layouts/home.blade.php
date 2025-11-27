    <!DOCTYPE html>
    <html lang="en" class="light-style layout-menu-fixed" dir="ltr"
        data-theme="theme-default" data-assets-path="{{ asset('sneat/assets/') }}"
        data-template="vertical-menu-template-free">

    <head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no"/>
    <title>@yield('title', 'Dashboard')</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('sneat/assets/img/favicon/favicon.ico') }}"/>

    {{-- Fonts & Icons --}}
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/fonts/boxicons.css') }}"/>

    {{-- Core CSS --}}
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/core.css') }}" class="template-customizer-core-css"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/css/demo.css') }}"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/libs/apex-charts/apex-charts.css') }}"/>

    {{-- Custom Theme (dark/light sync) --}}
    <style>
        :root {
        --bg-body: #f5f7fb;
        --bg-card: #ffffff;
        --text: #1f2937;
        --muted: #6b7280;
        --border: #e5e7eb;
        --menu-bg: #ffffff;
        --navbar-bg: #ffffff;
        --footer-bg: #ffffff;
        --input-bg: #ffffff;
        }
        
        body { background: var(--bg-body); color: var(--text); }
        .layout-menu.menu { background: var(--menu-bg) !important; }
        .bg-navbar-theme { background: var(--navbar-bg) !important; }
        .footer.bg-footer-theme { background: var(--footer-bg) !important; }
        .card, .modal-content, .dropdown-menu, .offcanvas, .list-group, .toast {
        background: var(--bg-card); color: var(--text); border-color: var(--border);
        }
        .form-control, .form-select {
        background: var(--input-bg)!important; color: var(--text)!important; border-color: var(--border)!important;
        }
    </style>

    {{-- Helpers & Config --}}
    <script src="{{ asset('sneat/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('sneat/assets/js/config.js') }}"></script>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Mode system/dark/light --}}
    <script>
        (function(){
        const key='theme-preference', pref=localStorage.getItem(key)||'system';
        const isDark=pref==='dark'||(pref==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-color-scheme', isDark?'dark':'light');
        })();
    </script>

    @stack('styles')
    </head>

    <body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

        {{-- Sidebar --}}
        @include('layouts.partials.sidebar')

        <div class="layout-page">
            {{-- Navbar --}}
            @include('layouts.partials.navbar')

            {{-- Content --}}
            <div class="content-wrapper">
            @yield('content')
            </div>

            {{-- Footer --}}
            <footer class="content-footer footer bg-footer-theme">
            <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                © <script>document.write(new Date().getFullYear())</script>, made with ❤️ by
                <a href="#" target="_blank" class="fw-bolder">ThemeSelection</a>
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
    <script src="{{ asset('sneat/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/libs/apex-charts/apex-charts.js') }}"></script>
    <script src="{{ asset('sneat/assets/js/main.js') }}"></script>

    @stack('scripts')
    </body>
    </html>
