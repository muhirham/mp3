<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Auth\LoginController;

// ADMIN
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PreOController;
use App\Http\Controllers\Admin\RestockApprovalController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\GoodReceivedController; 
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\CompanyController;
// WAREHOUSE
use App\Http\Controllers\Warehouse\WarehouseDashboardController;
use App\Http\Controllers\Warehouse\SalesController as WhSalesController;
use App\Http\Controllers\Warehouse\StockWhController;
use App\Http\Controllers\Warehouse\GRController;  

// SALES
use App\Http\Controllers\Warehouse\SalesHandoverController;
use App\Http\Controllers\Sales\HandoverOtpItemsController;

// OTHERS
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockLevelController;
use App\Models\SalesHandover;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Product;
use App\Models\PurchaseOrder;

/* ===== Auth ===== */
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'attempt'])->name('login.attempt');
});

Route::get('/dashboard', function () {
    $u = auth()->user();
    if (!$u) return redirect()->route('login');

    $home = $u->roles()->whereNotNull('home_route')->value('home_route') ?: 'admin.dashboard';

    // anti infinite redirect kalau home_route salah
    if ($home === 'dashboard' || !\Illuminate\Support\Facades\Route::has($home)) {
        $home = 'admin.dashboard';
    }

    return redirect()->route($home);
})->middleware(['auth','active'])->name('dashboard');



Route::get('/', fn() => redirect()->route('dashboard'))->middleware(['auth','active']);


Route::post('/logout', [LoginController::class,'logout'])->name('logout')->middleware(['auth','active']);


/* ===== Protected by menu keys ===== */
Route::middleware(['auth','active'])->group(function () {

    // ===== Dashboard (cukup auth, TIDAK pakai menu:xxx) =====
    Route::get('/admin',     [AdminController::class,'index'])->name('admin.dashboard');
    Route::get('/warehouse', [WarehouseDashboardController::class,'index'])->name('warehouse.dashboard');
    Route::get('/warehouse/dashboard/kpi', [WarehouseDashboardController::class, 'kpiAjax'])->name('wh.dashboard.kpi');
    Route::get('/warehouse/dashboard/inout', [WarehouseDashboardController::class,'inoutAjax'])
  ->name('warehouse.dashboard.inoutAjax');
    Route::get('/sales',     [WhSalesController::class,'dashboard'])->name('sales.dashboard');

    /* === Master Data (ADMIN KEYS) === */


    // key: roles
    Route::resource('roles', RoleController::class)
        ->except(['show','create','edit'])
        ->middleware('menu:roles');

    // key: warehouses
    Route::resource('warehouses', WarehouseController::class)
        ->only(['index','store','update','destroy'])
        ->middleware('menu:warehouses');

    // key: categories
    Route::get('categories/datatable', [CategoryController::class,'datatable'])
        ->name('categories.datatable')
        ->middleware('menu:categories');
    Route::resource('categories', CategoryController::class)
        ->only(['index','store','update','destroy'])
        ->middleware('menu:categories');

    // key: products
    Route::get('products/datatable', [ProductController::class,'datatable'])
        ->name('products.datatable')
        ->middleware('menu:products');
    Route::get('products/next-code', [ProductController::class,'nextCode'])
        ->name('products.next_code')
        ->middleware('menu:products');
    Route::resource('products', ProductController::class)
        ->except(['create','edit','show'])
        ->middleware('menu:products');

// Stock Adjustments
Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])
    ->name('stock-adjustments.index')
    ->middleware('menu:stock_adjustments');

Route::get('stock-adjustments/datatable', [StockAdjustmentController::class, 'datatable'])
    ->name('stock-adjustments.datatable')
    ->middleware('menu:stock_adjustments');

Route::get('stock-adjustments/products', [StockAdjustmentController::class, 'products'])
    ->name('stock-adjustments.products')
    ->middleware('menu:stock_adjustments');

Route::get('stock-adjustments/{adjustment}/detail', [StockAdjustmentController::class, 'detail'])
    ->name('stock-adjustments.detail')
    ->middleware('menu:stock_adjustments');

Route::get('stock-adjustments/ajax-products', [StockAdjustmentController::class, 'ajaxProducts'])
    ->name('stock-adjustments.ajax-products')
    ->middleware('menu:stock_adjustments');

Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])
    ->name('stock-adjustments.store')
    ->middleware('menu:stock_adjustments');

Route::get('/stock-adjustments/export/excel', [StockAdjustmentController::class, 'exportIndexExcel'])
    ->name('stock-adjustments.exportIndexExcel')
    ->middleware('menu:stock_adjustments');


    // key: suppliers
    Route::get('suppliers/datatable', [SupplierController::class,'datatable'])
        ->name('suppliers.datatable')
        ->middleware('menu:suppliers');
    Route::get('suppliers/next-code', [SupplierController::class,'nextCode'])
        ->name('suppliers.next_code')
        ->middleware('menu:suppliers');
    Route::resource('suppliers', SupplierController::class)
        ->only(['index','store','update','destroy'])
        ->middleware('menu:suppliers');

    // key: packages
    Route::get('packages/datatable', [PackageController::class,'datatable'])
        ->name('packages.datatable')
        ->middleware('menu:packages');
    Route::resource('packages', PackageController::class)
        ->except(['create','edit','show'])
        ->middleware('menu:packages');

    // key: users
    Route::resource('users', UserController::class)
        ->except(['show','create','edit'])
        ->middleware('menu:users');
    Route::post('users/bulk-destroy', [UserController::class,'bulkDestroy'])
        ->name('users.bulk-destroy')
        ->middleware('menu:users');

    /* === Stock Level (shared) – key: wh_stocklevel === */
    Route::get('/stock-level',           [StockLevelController::class,'index'])
        ->name('stocklevel.index')
        ->middleware('menu:wh_stocklevel');
    Route::get('/stock-level/datatable', [StockLevelController::class,'datatable'])
        ->name('stocklevel.datatable')
        ->middleware('menu:wh_stocklevel');
        

          /* === Purchase Orders === */
    // key: po
    Route::get('/po',                 [PreOController::class,'index'])
        ->name('po.index')
        ->middleware('menu:po');

    Route::post('/po/create', [PreOController::class,'create'])
    ->name('po.create')
    ->middleware('menu:po');

    Route::post('/po',                [PreOController::class,'store'])
        ->name('po.store')
        ->middleware('menu:po');

    Route::post('/po/from-requests',  [PreOController::class,'createFromRequests'])
        ->name('po.fromRequests')
        ->middleware('menu:po');

    Route::get('/po/{po}',            [PreOController::class,'edit'])
        ->name('po.edit')
        ->middleware('menu:po');

    Route::put('/po/{po}',            [PreOController::class,'update'])
        ->name('po.update')
        ->middleware('menu:po');

    Route::put('/po/{po}/receive',    [PreOController::class,'receive'])
        ->name('po.receive')
        ->middleware('menu:po');

    Route::post('/po/{po}/order',     [PreOController::class,'order'])
        ->name('po.order')
        ->middleware('menu:po');

    Route::post('/po/{po}/cancel',    [PreOController::class,'cancel'])
        ->name('po.cancel')
        ->middleware('menu:po');
        
    Route::post('/po/{po}/approve', [PreOController::class, 'approve'])
        ->name('po.approve')
        ->middleware('menu:po');


    Route::post('/po/{po}/approve-proc', [PreOController::class,'approveProcurement'])
        ->name('po.approve.proc')
        ->middleware('menu:po');

    Route::post('/po/{po}/reject-proc', [PreOController::class,'rejectProcurement'])
        ->name('po.reject.proc')
        ->middleware('menu:po');

    // CEO
    Route::post('/po/{po}/approve-ceo', [PreOController::class,'approveCeo'])
        ->name('po.approve.ceo')
        ->middleware('menu:po');

    Route::post('/po/{po}/reject-ceo', [PreOController::class,'rejectCeo'])
        ->name('po.reject.ceo')
        ->middleware('menu:po');
    
    Route::get('/po/{po}/pdf',        [PreOController::class,'exportPdf'])
        ->name('po.pdf')
        ->middleware('menu:po');
    
    Route::get('/po/{po}/excel',      [PreOController::class,'exportExcel'])
        ->name('po.excel')
        ->middleware('menu:po');

        Route::get('/po/table', [PreOController::class, 'table'])
        ->name('po.table')
        ->middleware('menu:po');

    Route::get('/po/export/index', [PreOController::class, 'exportIndexExcel'])
        ->name('po.export.index')
        ->middleware('menu:po');

    Route::get('/po/export/monthly', [PreOController::class, 'exportMonthlyExcel'])
        ->name('po.export.monthly')
        ->middleware('menu:po');
    
    Route::post('/po/{po}/gr', [GoodReceivedController::class, 'storeFromPo'])
        ->name('po.gr.store')
        ->middleware('menu:po');
    
    /* === Goods Received LIST (monitoring) – key: goodreceived === */
    Route::get('/good-received', [GoodReceivedController::class, 'index'])
        ->name('goodreceived.index')
        ->middleware('menu:goodreceived');


    // Ajukan permohonan delete GR untuk 1 GR (bukan PO)        
    Route::post('/good-received/{receipt}/cancel', [GoodReceivedController::class, 'cancelFromGr'])
        ->name('good-received.cancel')
        ->middleware('menu:goodreceived');


    Route::get('/good-received/{po}/detail', [GoodReceivedController::class, 'detail'])
        ->name('goodreceived.detail')
        ->middleware('menu:goodreceived');
        
    // Halaman daftar permohonan
    // ================== MASTER COMPANY ==================

    // Halaman list + form tambah / edit via modal
    Route::get('/companies', [CompanyController::class, 'index'])
        ->name('companies.index')
        ->middleware('menu:company');

    // Simpan company baru
    Route::post('/companies', [CompanyController::class, 'store'])
        ->name('companies.store')
        ->middleware('menu:company');

    // Update company (form di modal)
    Route::put('/companies/{company}', [CompanyController::class, 'update'])
        ->name('companies.update')
        ->middleware('menu:company');

    // Hapus (soft delete) company
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
        ->name('companies.destroy')
        ->middleware('menu:company');




    // APPROVE



    /* === Restock Approval – key: restock_request_ap === */
    Route::get('stockRequest',               [RestockApprovalController::class,'index'])
        ->name('stockRequest.index')
        ->middleware('menu:restock_request_ap');
    Route::get('stockRequest/json',          [RestockApprovalController::class,'json'])
        ->name('stockRequest.json')
        ->middleware('menu:restock_request_ap');
    Route::post('stockRequest/{id}/approve', [RestockApprovalController::class,'approve'])
        ->name('stockRequest.approve')
        ->middleware('menu:restock_request_ap');
    Route::post('stockRequest/{id}/reject',  [RestockApprovalController::class,'reject'])
        ->name('stockRequest.reject')
        ->middleware('menu:restock_request_ap');
    Route::post('stockRequest/bulk-po',      [RestockApprovalController::class,'bulkPO'])
        ->name('stockRequest.bulkpo')
        ->middleware('menu:restock_request_ap');

    Route::get('/restocks/{restock}/items',   [StockWhController::class,'items'])
        ->name('restocks.items')
        ->middleware('menu:wh_restock');

    /* === Warehouse – Restock + Sales Handover === */

    // key: wh_restock
    Route::get('/restocks',                   [StockWhController::class,'index'])
        ->name('restocks.index')
        ->middleware('menu:wh_restock');
    Route::get('/restocks/datatable',         [StockWhController::class,'datatable'])
        ->name('restocks.datatable')
        ->middleware('menu:wh_restock');
    Route::post('/restocks',                  [StockWhController::class,'store'])
        ->name('restocks.store')
        ->middleware('menu:wh_restock');
    Route::post('/restocks/{restock}/receive',[StockWhController::class,'receive'])
        ->name('restocks.receive')
        ->middleware('menu:wh_restock');
    Route::get('/restocks/export/excel', [StockWhController::class, 'exportExcel'])
        ->name('restocks.export.excel')
        ->middleware('menu:wh_restock');


    Route::get('/sales/handover/morning', [SalesHandoverController::class, 'morningForm'])
        ->name('sales.handover.morning')
        ->middleware('menu:wh_issue');

    Route::post('/sales/handover/morning/store', [SalesHandoverController::class, 'morningStoreAndSendOtp'])
        ->name('sales.handover.morning.store')
        ->middleware('menu:wh_issue');

    Route::post('/sales/handover/morning/verify', [SalesHandoverController::class, 'verifyMorningOtp'])
        ->name('sales.handover.morning.verify')
        ->middleware('menu:wh_issue');

    // SORE
    Route::get('/sales/handover/evening', [SalesHandoverController::class, 'eveningForm'])
        ->name('sales.handover.evening')
        ->middleware('menu:wh_reconcile');

    Route::get('/sales/handover/{handover}/items', [SalesHandoverController::class, 'eveningItems'])
        ->name('sales.handover.items')
        ->middleware('menu:wh_reconcile');

    Route::post('/sales/handover/{handover}/evening/save', [SalesHandoverController::class, 'eveningSave'])
        ->name('sales.handover.evening.save')
        ->middleware('menu:wh_reconcile');


    Route::post('/sales/handover/evening/verify', [SalesHandoverController::class, 'verifyEveningOtp'])
        ->name('sales.handover.evening.verify')
        ->middleware('menu:wh_reconcile');

    // GENERATE OTP SORE (untuk closing handover)
    Route::post('/warehouse/handovers/{handover}/evening/generate-otp',[SalesHandoverController::class, 'generateEveningOtp'])
        ->name('warehouse.handovers.evening.generate-otp')
        ->middleware('menu:wh_sales_reports');


    /* === Sales pages (SALES KEYS) === */

    // === Sales pages (SALES & WAREHOUSE) ===
    Route::get('/sales/{sales}/active-handover-count', [SalesHandoverController::class, 'getActiveCount']);


    Route::get('/warehouse/sales-reports', [SalesHandoverController::class,'warehouseSalesReport'])
        ->name('sales.report')
        ->middleware('menu:wh_sales_reports');

    Route::get('/warehouse/sales-reports/{handover}', [SalesHandoverController::class, 'warehouseSalesReportDetail'])
        ->name('sales.report.detail')
        ->middleware('menu:wh_sales_reports');

    Route::get('/sales/report', [SalesHandoverController::class,'salesReport'])
        ->name('daily.sales.report')
        ->middleware('menu:sales_daily');

    Route::get('/sales/report/{handover}', [SalesHandoverController::class, 'salesReportDetail'])
        ->name('daily.report.detail')
        ->middleware('menu:sales_daily');

    // key: sales_otp
    Route::get('/sales/otp-items', [HandoverOtpItemsController::class, 'index'])
        ->name('sales.otp.items')
        ->middleware('menu:sales_otp');

    Route::post('/sales/otp-items/verify', [HandoverOtpItemsController::class, 'verify'])
        ->name('sales.otp.items.verify')
        ->middleware('menu:sales_otp');

    Route::get('/sales/handover/otps', [SalesHandoverController::class, 'salesOtpIndex'])
        ->name('sales.handover.otps')
        ->middleware('menu:sales-handover-otp');

    Route::post('/sales/otp-items/payments', [HandoverOtpItemsController::class, 'savePayments'])
        ->name('sales.otp.items.payments.save')
        ->middleware('menu:sales_otp');

    // ====== WAREHOUSE: APPROVAL PEMBAYARAN HANDOVER ======
// FORM APPROVAL (GET) – sudah benar
    Route::get('/warehouse/handovers/{handover}/payments', [SalesHandoverController::class, 'paymentApprovalForm'])
        ->name('warehouse.handovers.payments.form')
        ->middleware('menu:wh_sales_reports');

    // SIMPAN APPROVAL (POST)
    Route::post('/warehouse/handovers/{handover}/payments', [SalesHandoverController::class, 'paymentApprovalSave'])
        ->name('warehouse.handovers.payments.approve')
        ->middleware('menu:wh_sales_reports');


    // Reject 1 item payment (dipanggil via AJAX dari tabel item)
    Route::post('/warehouse/handovers/{handover}/payments/reject', [SalesHandoverController::class, 'rejectPayment'])
        ->name('warehouse.handovers.payments.reject')
        ->middleware('menu:wh_sales_reports');

    Route::get('/sales/return', [WhSalesController::class,'return'])
        ->name('sales.return')
        ->middleware('menu:sales_return');
        

    /* === Reports (umum) – key: reports === */
    
    Route::resource('/reports', ReportController::class)
        ->only(['index'])
        ->middleware('menu:reports');
    
        
});
