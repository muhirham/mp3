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
            'label' => 'Units of Measure',
            'route' => 'packages.index',
            'group' => 'master',
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
            'group' => 'procurement',
            'icon'  => 'bx bx-store-alt',
        ],
        [
            'key'   => 'stock_adjustments',
            'label' => 'Adjustment',
            'route' => 'stock-adjustments.index',
            'group' => 'inventory',
            'icon'  => 'bx bx-adjust',
        ],
                [
            'key'   => 'approval_stock_damage',
            'label' => 'Damage Approvals',
            'route' => 'damaged-stocks.approval',
            'group' => 'inventory',
            'icon'  => 'bx bx-shield-quarter',
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
            'label' => 'Inventory Balances',
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
            'key'   => 'wh_issue',
            'label' => 'Stock Handover (Out)',
            'route' => 'sales.handover.morning',
            'group' => 'warehouse',
            'icon'  => 'bx bx-up-arrow-circle',
        ],
        [
            'key'   => 'wh_reconcile',
            'label' => 'Sales Reconciliation',
            'route' => 'sales.handover.evening',
            'group' => 'warehouse',
            'icon'  => 'bx bx-check-shield',
        ],
        [
            'key'   => 'wh_direct_sales',
            'label' => 'Direct Sales',
            'route' => 'warehouse.direct_sales.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-cart-alt',
        ],
        [
            'key'   => 'wh_sales_reports',
            'label' => 'Sales Reports',
            'route' => 'sales.report',
            'group' => 'warehouse',
            'icon'  => 'bx bx-bar-chart-alt-2',
        ],
        [
            'key'   => 'wh_transfers',
            'label' => 'Stock Transfers',
            'route' => 'warehouse-transfers.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-transfer',
        ],
        [
            'key'   => 'sales_return_approval',
            'label' => 'Sales Return Approval',
            'route' => 'warehouse.returns.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-check-circle',
        ],
        [
            'key'   => 'wh_damaged_stocks',
            'label' => 'Damaged & Expired Stocks',
            'route' => 'damaged-stocks.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-error-alt',
        ],
        [
            'key'   => 'sales_request_approval',
            'label' => 'Sales Request Approval',
            'route' => 'warehouse.stock-requests.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-file-blank',
        ],

        [
            'key'   => 'wh_settlements',
            'label' => 'Daily Settlements',
            'route' => 'warehouse.settlements.index',
            'group' => 'warehouse',
            'icon'  => 'bx bx-wallet',
        ],

        // ================== PROCUREMENT ==================
        [
            'key'   => 'wh_restock', // Request Restock Adm WH (view warehouse)
            'label' => 'Warehouse Restock Request',
            'route' => 'restocks.index',
            'group' => 'procurement',
            'icon'  => 'bx bx-cart-add',
        ],
        [
            'key'   => 'restock_request_ap',
            'label' => 'Restock Approval',
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
            'route' => 'daily.sales.report',
            'group' => 'sales',
            'icon'  => 'bx bx-calendar-check',
        ],
        // ================== FINANCE ==================
        [
            'key'   => 'finance_settlements',
            'label' => 'Warehouse Settlements',
            'route' => 'finance.settlements.index',
            'group' => 'finance',
            'icon'  => 'bx bx-receipt',
        ],
        [
            'key'   => 'sales_otp',  // <=== KEY BARU
            'label' => 'Issued Stock History',
            'route' => 'sales.otp.items',
            'group' => 'sales',
            'icon'  => 'bx bx-key', // bebas mau ganti icon apa
        ],
        [
            'key'   => 'sales_return',
            'label' => 'Sales Return',
            'route' => 'sales.returns.index',
            'group' => 'sales',
            'icon'  => 'bx bx-undo',
        ],
        [
            'key'        => 'sales-handover-otp',
            'label'      => 'Handover Verification',
            'route'      => 'sales.handover.otps',
            'group'      => 'sales',
            'icon'       => 'bx bx-key',
        ],
        [
            'key'   => 'sales_request',
            'label' => 'Stock Requests',
            'route' => 'sales-request.index',
            'group' => 'sales',
            'icon'  => 'bx bx-cart-download',
        ],
        // ================== MASTER DATA ==================
        [
        'key'   => 'company',
        'label' => 'Company',
        'icon'  => 'bx bx-buildings',
        'route' => 'companies.index',
        'group' => 'master', // atau group apa pun yang lu pake
        ],
        [
            'key'   => 'users',
            'label' => 'Users',
            'route' => 'users.index',
            'group' => 'master',
            'icon'  => 'bx bx-user',
        ],
        [
            'key'   => 'roles',
            'label' => 'Roles & Permissions',
            'route' => 'roles.index',
            'group' => 'master',
            'icon'  => 'bx bx-shield-quarter',
        ],
        [
            'key'   => 'bom',
            'label' => 'Manufacturing / BOM',
            'route' => 'bom.index',
            'group' => 'master',
            'icon'  => 'bx bx-cog',
        ]

    ],

    // ==== LABEL & ICON GRUP UNTUK DROPDOWN PARENT ====
    'groups' => [
        'inventory'   => ['label' => 'Inventory',   'icon' => 'bx bx-box'],
        'warehouse'   => ['label' => 'Warehouse',   'icon' => 'bx bx-buildings'],
        'procurement' => ['label' => 'Procurement', 'icon' => 'bx bx-cart'],
        'sales'       => ['label' => 'Sales',       'icon' => 'bx bx-line-chart'],
        'finance'     => ['label' => 'Finance',     'icon' => 'bx bx-money'],
        'master'      => ['label' => 'Master Data', 'icon' => 'bx bx-slider-alt'],
    ],

    // ==== OPSI TETAP untuk Home Route combobox ====
    'home_candidates' => [
        ['label' => 'Admin Dashboard',     'route' => 'admin.dashboard'],
        ['label' => 'Warehouse Dashboard', 'route' => 'warehouse.dashboard'],
        ['label' => 'Sales Dashboard',     'route' => 'sales.dashboard'],
    ],
];
