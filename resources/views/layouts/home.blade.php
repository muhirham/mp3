    <!DOCTYPE html>
    <html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
        data-assets-path="{{ asset('sneat/assets/') }}" data-template="vertical-menu-template-free">

    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
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

    {{-- KURIR GLOBAL (Real-time Handover & Stock & Sales Return) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Echo) {
                const currentUserId = @json(auth()->id());
                const isManagement  = @json(auth()->user()->hasRole('admin') || auth()->user()->hasRole('superadmin') || auth()->user()->hasRole('warehouse'));
                const isSuperOrAdmin = @json(auth()->user()->hasRole('admin') || auth()->user()->hasRole('superadmin'));
                const myWarehouseId = @json(auth()->user()->warehouse_id);

                window.Echo.channel('sales-channel')
                    .listen('.handover-updated', (e) => {
                        console.log('Real-time Signal Received:', e);
                        
                        // 1. Logic buat Sidebar & Dashboard: Selalu update angka kalau relevant
                        if (e.salesId == currentUserId || isManagement) {
                            if (window.refreshSidebarBadges) {
                                window.refreshSidebarBadges();
                            }
                        }

                        // 2. Logic buat Sales (HP): Buka Modal atau Update Tabel
                        if (e.salesId == currentUserId) {
                             if (e.updateType === 'otp_sent') {
                                 if (window.triggerOtpModal) window.triggerOtpModal();
                                 if (window.loadHdoList) window.loadHdoList();
                             }
                             if (['verified', 'payment_decided', 'payment_draft_saved'].includes(e.updateType)) {
                                 if (window.refreshHandoverTable) window.refreshHandoverTable();
                             }
                        }

                        // 3. Logic buat Admin (Laptop): Update list approval sore
                        if (isManagement && window.location.href.indexOf('handover') > -1) {
                             if (e.updateType === 'payment_submitted') {
                                 if (window.refreshEveningList) window.refreshEveningList();
                             }
                             if (e.updateType === 'verified') {
                                 if (window.refreshMorningStatus) window.refreshMorningStatus();
                             }
                        }
                    })
                    .listen('.sales-return-updated', (e) => {
                        console.log('[SalesReturn Global] Event received:', e);

                        // ✅ SUPERADMIN & ADMIN: dapet semua event apapun tipenya
                        if (isSuperOrAdmin) {
                            console.log('[Debug] Super/Admin access granted. Refreshing table & badges...');
                            if (window.refreshReturnTable) window.refreshReturnTable();
                            if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                            return;
                        }

                        // ✅ WAREHOUSE ADMIN: reload untuk KEDUA tipe event (new_return & status_updated)
                        if (isManagement) {
                            console.log('[Debug] Warehouse Admin check:', { eventWH: e.warehouseId, myWH: myWarehouseId });
                            if (e.warehouseId == myWarehouseId) {
                                if (window.refreshReturnTable) window.refreshReturnTable();
                                if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                            } else {
                                console.warn('[Debug] Warehouse ID mismatch. Event ignored.');
                            }
                            return;
                        }

                        // ✅ SALES: reload kalau status return mereka berubah
                        if (e.updateType === 'status_updated') {
                            console.log('[Debug] Sales check:', { eventSales: e.salesId, currentSales: currentUserId });
                            if (e.salesId == currentUserId) {
                                if (window.refreshReturnTable) window.refreshReturnTable();
                                if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                            } else {
                                console.warn('[Debug] Sales ID mismatch. Event ignored.');
                            }
                        }
                    });
            }
        });
    </script>
    {{-- 🔔 SIDEBAR BADGE SYSTEM --}}
    <script>
        // Map: menu key → notification type(s) yang relevan
        const SIDEBAR_BADGE_MAP = {
            'sales_return_approval': 'new_return',                            // WH Admin & Superadmin
            'sales_return'         : 'return_rejected',                       // Sales (perlu resubmit)
            'wh_reconcile'         : 'handover_payment_submitted',            // WH Admin
            'sales_otp'            : 'handover_otp_sent,handover_payment_rejected', // Sales (Issued Stock History)
            'sales-handover-otp'   : 'handover_otp_sent',                       // Sales (Handover Verification)
        };

        // Fetch badge count dari server dan inject ke sidebar
        function refreshSidebarBadges() {
            Object.entries(SIDEBAR_BADGE_MAP).forEach(([menuKey, type]) => {
                const container = document.querySelector(`#menu-item-${menuKey} .badge-container`);
                if (!container) return;

                fetch(`/notifications/badge?type=${type}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.count > 0) {
                        container.innerHTML = `<span class="badge rounded-pill bg-danger" style="font-size:10px;min-width:18px;">${data.count}</span>`;
                    } else {
                        container.innerHTML = '';
                    }
                })
                .catch(() => {}); // silent fail
            });
        }

        // Auto-clear badge saat user buka halaman yang relevan
        function clearBadgeOnOpen(menuKey, type) {
            const isActive = document.querySelector(`#menu-item-${menuKey}.active`);
            if (!isActive) return;

            fetch('/notifications/mark-read-by-type', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ type })
            }).catch(() => {});
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Load badge angka dari DB saat halaman dibuka
            refreshSidebarBadges();
            // Badge TIDAK di-clear saat dibuka — hanya hilang saat ada action (approve/reject)
        });

        // Expose global agar bisa dipanggil dari Echo listener
        window.refreshSidebarBadges = refreshSidebarBadges;
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
