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
use App\Http\Controllers\Admin\ProductStockController;
use App\Http\Controllers\Admin\GoodReceivedController; 
use App\Http\Controllers\Admin\StockAdjustmentController;

use App\Http\Controllers\GrDeleteRequestController;
// WAREHOUSE
use App\Http\Controllers\Warehouse\WarehouseDashboardController;
use App\Http\Controllers\Warehouse\SalesController as WhSalesController;
use App\Http\Controllers\Warehouse\SalesHandoverController;
use App\Http\Controllers\Warehouse\StockWhController;
use App\Http\Controllers\Warehouse\GRController;  

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
    // mendarat ke home_route role pertama yg punya nilai
    $home = $u->roles()->whereNotNull('home_route')->value('home_route') ?: 'admin.dashboard';
    return redirect()->route($home);
})->middleware('auth')->name('dashboard');

Route::get('/', fn() => redirect()->route('dashboard'))->middleware('auth');

Route::post('/logout', [LoginController::class,'logout'])->name('logout')->middleware('auth');

/* ===== Protected by menu keys ===== */
Route::middleware('auth')->group(function () {

    // ===== Dashboard (cukup auth, TIDAK pakai menu:xxx) =====
    Route::get('/admin',     [AdminController::class,'index'])->name('admin.dashboard');
    Route::get('/warehouse', [WarehouseDashboardController::class,'index'])->name('warehouse.dashboard');
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

     // key: stockproducts

    // key: stock_adjustments
    Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])
        ->name('stock-adjustments.index')
        ->middleware('menu:stock_adjustments');

    Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])
        ->name('stock-adjustments.store')
        ->middleware('menu:stock_adjustments');

    Route::get('stock-adjustments/ajax-products', [StockAdjustmentController::class, 'ajaxProducts'])
        ->name('stock-adjustments.ajax-products')
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

    // === Goods Received dari PO MANUAL (1 GR per PO) ===
    
    Route::get('/po/{po}/pdf',        [PreOController::class,'exportPdf'])
    ->name('po.pdf')
    ->middleware('menu:po');
    
    Route::get('/po/{po}/excel',      [PreOController::class,'exportExcel'])
    ->name('po.excel')
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
        
    // Halaman daftar permohonan
    Route::get('/good-received/delete-requests',[GrDeleteRequestController::class, 'index'])
    ->name('goodreceived.delete-requests.index')
    ->middleware('menu:goodreceived_delete');

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

    // key: wh_issue (pagi)
    Route::get('/sales/handover/morning', function () {
        $me = auth()->user();

        $whQuery = Warehouse::query();
        if ($me->warehouse_id) $whQuery->where('id', $me->warehouse_id);

        if (Schema::hasColumn('warehouses','warehouse_name')) {
            $whQuery->orderBy('warehouse_name');
            $warehouses = $whQuery->get(['id', DB::raw('warehouse_name as name')]);
        } elseif (Schema::hasColumn('warehouses','name')) {
            $whQuery->orderBy('name');
            $warehouses = $whQuery->get(['id','name']);
        } else {
            $warehouses = $whQuery->get(['id'])->map(fn($w)=>(object)['id'=>$w->id,'name'=>'Warehouse #'.$w->id]);
        }

        $salesUsers = User::whereHas('roles', fn($q)=>$q->where('slug','sales'))
            ->when($me->warehouse_id, fn($q)=>$q->where('warehouse_id',$me->warehouse_id))
            ->orderBy('name')->get(['id','name','warehouse_id']);

        $products = Product::select('id','name','product_code')->orderBy('name')->get();
        return view('wh.handover_morning', compact('me','warehouses','salesUsers','products'));
    })->name('sales.handover.morning')
      ->middleware('menu:wh_issue');

    Route::post('/sales/handover/issue', [SalesHandoverController::class,'issue'])
        ->name('sales.handover.issue')
        ->middleware('menu:wh_issue');

    // key: wh_reconcile (sore + OTP + rekonsiliasi)
    Route::get('/sales/handover/evening', function () {
        $me = auth()->user();
        $handovers = SalesHandover::with('sales:id,name')
            ->whereIn('status',['issued','waiting_otp'])
            ->when($me->warehouse_id, fn($q)=>$q->where('warehouse_id',$me->warehouse_id))
            ->orderBy('handover_date','desc')
            ->get(['id','code','status','sales_id','handover_date','warehouse_id'])
            ->map(fn($h)=> (object)[
                'id'=>$h->id,'code'=>$h->code,'status'=>$h->status,
                'sales_id'=>$h->sales_id,'handover_date'=>$h->handover_date,
                'sales_name'=>$h->sales->name ?? null,
            ]);

        return view('wh.handover_evening', compact('me','handovers'));
    })->name('sales.handover.evening')
      ->middleware('menu:wh_reconcile');

    Route::post('/sales/handover/{handover}/generate-otp', [SalesHandoverController::class,'generateOtp'])
        ->name('sales.handover.otp')
        ->middleware('menu:wh_reconcile');

    Route::post('/sales/handover/{handover}/reconcile',    [SalesHandoverController::class,'reconcile'])
        ->name('sales.handover.reconcile')
        ->middleware('menu:wh_reconcile');

        /* <<< ADD THIS */
        Route::get('/sales/handover/{handover}/items', [SalesHandoverController::class,'items'])
            ->name('sales.handover.items')
            ->middleware('menu:wh_reconcile');

    /* === Sales pages (SALES KEYS) === */

    // key: sales_daily
    Route::get('/sales/report', [WhSalesController::class,'report'])
        ->name('sales.report')
        ->middleware('menu:sales_daily');

    // key: sales_return
    Route::get('/sales/return', [WhSalesController::class,'return'])
        ->name('sales.return')
        ->middleware('menu:sales_return');

    /* === Reports (umum) – key: reports === */
    Route::resource('/reports', ReportController::class)
        ->only(['index'])
        ->middleware('menu:reports');
});
