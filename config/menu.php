<?php

return [

    // ==== REGISTRY MENU (untuk checkbox & sidebar) ====
    'items' => [

        // ================== INVENTORY ==================
        [
            'key'   => 'products',
            'label' => 'Products',
            'route' => 'products.index',
            'group' => 'inventory',
            'icon'  => 'bx bx-cube-alt',
        ],
        [
            'key'   => 'packages',
            'label' => 'UOM',
            'route' => 'packages.index',
            'group' => 'inventory',
            'icon'  => 'bx bx-package',
        ],
        [
            'key'   => 'categories',
            'label' => 'Categories',
            'route' => 'categories.index',
            'group' => 'inventory',
            'icon'  => 'bx bx-category',
        ],
        [
            'key'   => 'suppliers',
            'label' => 'Suppliers',
            'route' => 'suppliers.index',
            'group' => 'inventory',
            'icon'  => 'bx bx-store-alt',
        ],
        [
            'key'   => 'stockproducts',
            'label' => 'Stock Products',
            'route' => 'stockproducts.index',   // nanti kamu isi routenya
            'group' => 'inventory',
            'icon'  => 'bx bx-archive',
        ],
        [
            'key'   => 'stock_adjustments',
            'label' => 'Stock Adjustments',
            'route' => 'stock-adjustments.index',
            'group' => 'inventory',
            'icon'  => 'bx bx-adjust',
        ],

        // ================== WAREHOUSE ==================
        [
            'key'   => 'warehouses',
            'label' => 'Warehouses',
            'route' => 'warehouses.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-buildings',
        ],
        [
            'key'   => 'wh_stocklevel',
            'label' => 'Stock Gudang',
            'route' => 'stocklevel.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-layer',
        ],
        [
            'key'   => 'goodreceived',
            'label' => 'Goods Received',
            'route' => 'goodreceived.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-download',
        ],
        [
            'key'   => 'goodreceived_delete',
            'label' => 'GR Delete Requests',
            'route' => 'goodreceived.delete-requests.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-trash',
        ],
        [
            'key'   => 'wh_issue',
            'label' => 'Issue ke Sales (Pagi)',
            'route' => 'sales.handover.morning',
            'group' => 'warehouse',
            'icon'  => 'bx bx-up-arrow-circle',
        ],
        [
            'key'   => 'wh_reconcile',
            'label' => 'Reconcile + OTP (Sore)',
            'route' => 'sales.handover.evening',
            'group' => 'warehouse',
            'icon'  => 'bx bx-check-shield',
        ],
        [
            'key'   => 'wh_sales_reports',
            'label' => 'Sales Reports',
            'route' => 'sales.report',
            'group' => 'warehouse',
            'icon'  => 'bx bx-bar-chart-alt-2',
        ],

        // ================== PROCUREMENT ==================
        [
            'key'   => 'wh_restock', // Request Restock Adm WH (view warehouse)
            'label' => 'Request Restock Admin WH',
            'route' => 'restocks.index',
            'group' => 'procurement',
            'icon'  => 'bx bx-cart-add',
        ],
        [
            'key'   => 'restock_request_ap',
            'label' => 'Approval Restock',
            'route' => 'stockRequest.index',
            'group' => 'procurement',
            'icon'  => 'bx bx-transfer-alt',
        ],
        [
            'key'   => 'po',
            'label' => 'Purchase Orders',
            'route' => 'po.index',
            'group' => 'procurement',
            'icon'  => 'bx bx-receipt',
        ],

        // ================== SALES ==================
        [
            'key'   => 'sales_daily',
            'label' => 'Daily Report',
            'route' => 'sales.report',
            'group' => 'sales',
            'icon'  => 'bx bx-calendar-check',
        ],
        [
            'key'   => 'sales_return',
            'label' => 'Return Products',
            'route' => 'sales.return',
            'group' => 'sales',
            'icon'  => 'bx bx-undo',
        ],

        // ================== REPORT / TRANSACTIONS ==================
        [
            'key'   => 'transactions',
            'label' => 'Transactions',
            'route' => 'transactions.index', // masih placeholder
            'group' => 'reports',
            'icon'  => 'bx bx-transfer',
        ],
        [
            'key'   => 'reports',
            'label' => 'Reports',
            'route' => 'reports.index',
            'group' => 'reports',
            'icon'  => 'bx bx-file',
        ],

        // ================== MASTER DATA ==================
        [
            'key'   => 'users',
            'label' => 'Users',
            'route' => 'users.index',
            'group' => 'master',
            'icon'  => 'bx bx-user',
        ],
        [
            'key'   => 'roles',
            'label' => 'Roles & Sidebar',
            'route' => 'roles.index',
            'group' => 'master',
            'icon'  => 'bx bx-shield-quarter',
        ],
    ],

    // ==== LABEL & ICON GRUP UNTUK DROPDOWN PARENT ====
    'groups' => [
        'inventory'   => ['label' => 'Inventory',   'icon' => 'bx bx-box'],
        'warehouse'   => ['label' => 'Warehouse',   'icon' => 'bx bx-buildings'],
        'procurement' => ['label' => 'Procurement', 'icon' => 'bx bx-cart'],
        'sales'       => ['label' => 'Sales',       'icon' => 'bx bx-line-chart'],
        'reports'     => ['label' => 'Reports',     'icon' => 'bx bx-file'],
        'master'      => ['label' => 'Master Data', 'icon' => 'bx bx-slider-alt'],
    ],

    // ==== OPSI TETAP untuk Home Route combobox ====
    'home_candidates' => [
        ['label' => 'Admin Dashboard',     'route' => 'admin.dashboard'],
        ['label' => 'Warehouse Dashboard', 'route' => 'warehouse.dashboard'],
        ['label' => 'Sales Dashboard',     'route' => 'sales.dashboard'],
    ],
];
