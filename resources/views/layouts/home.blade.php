    <!DOCTYPE html>
    <html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
        data-assets-path="{{ asset('sneat/assets/') }}" data-template="vertical-menu-template-free">

    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
        <title>@yield('title', 'Dashboard')</title>

        {{-- Favicon --}}
        <link rel="icon" type="image/x-icon" href="{{ asset('sneat/assets/img/favicon/favicon.ico') }}" />

        {{-- Fonts & Icons --}}
        <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
            rel="stylesheet" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/fonts/boxicons.css') }}" />

        {{-- Core CSS --}}
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/core.css') }}"
            class="template-customizer-core-css" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/theme-default.css') }}"
            class="template-customizer-theme-css" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/css/demo.css') }}" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
        <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/libs/apex-charts/apex-charts.css') }}" />

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

            body {
                background: var(--bg-body);
                color: var(--text);
            }

            .layout-menu.menu {
                background: var(--menu-bg) !important;
            }

            .bg-navbar-theme {
                background: var(--navbar-bg) !important;
            }

            .footer.bg-footer-theme {
                background: var(--footer-bg) !important;
            }

            .card,
            .modal-content,
            .dropdown-menu,
            .offcanvas,
            .list-group,
            .toast {
                background: var(--bg-card);
                color: var(--text);
                border-color: var(--border);
            }

            .form-control,
            .form-select {
                background: var(--input-bg) !important;
                color: var(--text) !important;
                border-color: var(--border) !important;
            }
        </style>

        {{-- Helpers & Config --}}
        <script src="{{ asset('sneat/assets/vendor/js/helpers.js') }}"></script>
        <script src="{{ asset('sneat/assets/js/config.js') }}"></script>

        {{-- SweetAlert2 --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        {{-- Mode system/dark/light --}}
        <script>
            (function() {
                const key = 'theme-preference',
                    pref = localStorage.getItem(key) || 'system';
                const isDark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)')
                    .matches);
                document.documentElement.setAttribute('data-color-scheme', isDark ? 'dark' : 'light');
            })();
        </script>

        @vite(['resources/js/app.js'])

    {{-- KURIR GLOBAL (Real-time Handover & Stock) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Echo) {
                const currentUserId = @json(auth()->id());
                const isManagement  = @json(auth()->user()->hasRole('admin') || auth()->user()->hasRole('superadmin') || auth()->user()->hasRole('warehouse'));

                window.Echo.channel('sales-channel')
                    .listen('.handover-updated', (e) => {
                        console.log('Real-time Signal Received:', e);
                        console.log('User Role Management Status:', isManagement);
                        
                        // 1. Logic buat Sidebar & Dashboard: Selalu update angka kalau relevant
                        if (e.salesId == currentUserId || isManagement) {
                            if (window.refreshSidebarBadges) {
                                console.log('Triggering Sidebar Badge Refresh...');
                                window.refreshSidebarBadges();
                            }
                        }

                        // 2. Logic buat Sales (HP): Buka Modal atau Update Tabel
                        if (e.salesId == currentUserId) {
                             if (e.updateType === 'otp_sent') {
                                 console.log('Triggering Sales OTP Modal...');
                                 if (window.triggerOtpModal) window.triggerOtpModal();
                                 if (window.loadHdoList) window.loadHdoList();
                             }
                             if (['verified', 'payment_decided', 'payment_draft_saved'].includes(e.updateType)) {
                                 console.log('Triggering Sales Table Refresh...');
                                 if (window.refreshHandoverTable) window.refreshHandoverTable();
                             }
                        }

                        // 3. Logic buat Admin (Laptop): Update list approval sore
                        if (isManagement && window.location.href.indexOf('handover') > -1) {
                             console.log('Event Match for Management Page Refresh:', e.updateType);
                             if (e.updateType === 'payment_submitted') {
                                 if (window.refreshEveningList) {
                                     console.log('Calling window.refreshEveningList()...');
                                     window.refreshEveningList();
                                 } else {
                                     console.warn('window.refreshEveningList is NOT defined on this page!');
                                 }
                             }
                             if (e.updateType === 'verified') {
                                 if (window.refreshMorningStatus) {
                                     console.log('Calling window.refreshMorningStatus()...');
                                     window.refreshMorningStatus();
                                 }
                             }
                        }
                    });
            }
        });
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
                        <div
                            class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                ©
                                <script>
                                    document.write(new Date().getFullYear())
                                </script>, made with MANDAU || V.0.3.2

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
        {{-- <script src="{{ asset('sneat/assets/vendor/libs/apex-charts/apex-charts.js') }}"></script> --}}
        <script src="{{ asset('sneat/assets/js/main.js') }}"></script>

        @stack('scripts')

        <!-- Global Anti Double-Click & Submit Loader -->
        <script>
            // 🔥 Fungsi Sapu Bersih: Reset SEMUA tombol di form biar nggak ada yang muter terus
            window.resetSubmitButton = function(form) {
                if (!form) return;
                form.dataset.submitting = 'false';
                
                // Cari semua tombol yang mungkin dipake buat submit
                const btns = form.querySelectorAll('button[type="submit"], button.btn, input[type="submit"]');
                btns.forEach(btn => {
                    if (btn.dataset.originalHtml) {
                        btn.innerHTML = btn.dataset.originalHtml;
                        btn.style.pointerEvents = 'auto';
                        btn.style.opacity = '1';
                        // Balikin lebar asli kalau tadi kita paksa set
                        btn.style.width = btn.dataset.originalWidth || '';
                        
                        delete btn.dataset.originalHtml;
                        delete btn.dataset.originalWidth;
                    }
                });
            };

            document.addEventListener('submit', function(e) {
                const form = e.target;

                if (form.dataset.submitting === 'true') {
                    e.preventDefault();
                    return;
                }

                const btn = e.submitter || form.querySelector('button[type="submit"]');
                
                if (!e.defaultPrevented) {
                    form.dataset.submitting = 'true';
                }

                if (btn && !btn.dataset.originalHtml) {
                    // Simpan state asli
                    btn.dataset.originalHtml = btn.innerHTML;
                    btn.dataset.originalWidth = btn.style.getPropertyValue('width') || '';

                    // Biar nggak melar, kita kunci lebarnya ke lebar saat ini (computed)
                    const currentWidth = btn.getBoundingClientRect().width;
                    btn.style.width = currentWidth + 'px';

                    btn.style.pointerEvents = 'none';
                    btn.style.opacity = '0.7';

                    if (!btn.innerHTML.includes('spinner-border')) {
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + btn.innerHTML;
                    }
                }

                // Failsafe 10 detik
                setTimeout(() => { if (form.dataset.submitting === 'true') window.resetSubmitButton(form); }, 10000);
            });
        </script>
    </body>

    </html>
