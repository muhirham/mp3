<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Package;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private string $codePrefix = 'PRD-';

    protected function ensureProductPermission(string $permission = 'products.view'): void
    {
        $me = auth()->user();

        if (! $me || ! $me->hasPermission($permission)) {
            abort(403, 'Anda tidak punya izin untuk mengakses modul Products.');
        }
    }

    public function index()
    {
        $this->ensureProductPermission('products.view');

        // data buat filter & form
        $categories = Category::select('id','category_name')
            ->orderBy('category_name')
            ->get();

        $suppliers  = Supplier::select('id','name')
            ->orderBy('name')
            ->get();

        $packages   = Package::select('id','package_name')
            ->orderBy('package_name')
            ->get();

        $warehouses = Warehouse::select('id', 'warehouse_name')
            ->orderBy('warehouse_name')
            ->get();

        $nextProductCode = $this->generateNextCode();

        // SUMMARY


        return view('admin.masterdata.products', compact(
            'categories',
            'suppliers',
            'packages',
            'warehouses',
            'nextProductCode',
        ));
    }

public function datatable(Request $request)
{
    $this->ensureProductPermission('products.view');

    try {
        $draw        = (int) $request->input('draw', 1);
        $start       = (int) $request->input('start', 0);
        $length      = (int) $request->input('length', 10);
        $orderColIdx = (int) $request->input('order.0.column', 1);
        $orderDir    = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $search      = trim((string) $request->input('search.value', ''));

        // === FILTER DROPDOWN (dikirim dari JS) ===
        $filterCategory = $request->input('category'); // isi: nama kategori
        $filterSupplier = $request->input('supplier'); // isi: nama supplier

        $q = DB::table('products as p')
            ->leftJoin('categories as c','c.id','=','p.category_id')
            ->leftJoin('packages  as g','g.id','=','p.package_id')
            ->leftJoin('suppliers as s','s.id','=','p.supplier_id');
            $q->whereNull('p.deleted_at');


        // ====== JOIN STOK CENTRAL (owner_type = 'pusat') ======
        $stockExpr = '0';
        if (Schema::hasTable('stock_levels')) {
            $stockSub = DB::table('stock_levels as sl')
                ->selectRaw('sl.product_id, SUM(sl.quantity) as qty_stock')
                ->where('sl.owner_type', 'pusat')
                ->groupBy('sl.product_id');

            $q->leftJoinSub($stockSub, 'st', 'st.product_id', '=', 'p.id');
            $stockExpr = 'COALESCE(st.qty_stock,0)';
        }

        $q->select([
            'p.id',
            'p.product_code',
            'p.name',
            'p.category_id',
            'p.package_id',
            'p.supplier_id',
            'p.description',
            'p.purchasing_price',
            'p.selling_price',
            'p.stock_minimum',
            'p.product_type',
            'p.standard_cost',
            'p.is_active',
            DB::raw("$stockExpr AS total_stock"),
            'c.category_name',
            DB::raw('g.package_name AS package_name'),
            DB::raw('s.name AS supplier_name'),
        ]);

        // ====== FILTER DROPDOWN ======
        if ($filterCategory !== null && $filterCategory !== '') {
            $q->where('c.category_name', $filterCategory);
        }

        if ($filterSupplier !== null && $filterSupplier !== '') {
            $q->where('s.name', $filterSupplier);
        }

        // ====== SEARCH GLOBAL (navbar) ======
        if ($search !== '') {
            $q->where(function($w) use ($search){
                $like = "%{$search}%";
                $w->where('p.product_code','like',$like)
                  ->orWhere('p.name','like',$like)
                  ->orWhere('c.category_name','like',$like)
                  ->orWhere('g.package_name','like',$like)
                  ->orWhere('s.name','like',$like)
                  ->orWhere('p.description','like',$like);
            });
        }

        // ====== ORDER MAP (sesuai index kolom DataTables) ======
        // 0 = rownum
        // 1 = product_code
        // 2 = name
        // 3 = category
        // 4 = package (UOM)
        // 5 = supplier
        // 6 = description
        // 7 = stock
        // 8 = min_stock
        // 9 = status (badge) -> nggak bisa sort
        // 10 = purchasing_price
        // 11 = selling_price
        // 12 = actions
        $orderMap = [
            1  => 'p.product_code',
            2  => 'p.name',
            3  => 'c.category_name',
            4  => 'p.product_type',
            5  => 'g.package_name',
            6  => 's.name',
            7  => 'p.description',
            8  => 'total_stock',
            9  => 'p.stock_minimum',
            11 => 'p.purchasing_price',
            12 => 'p.standard_cost',
            13 => 'p.selling_price',
        ];

        $orderCol = $orderMap[$orderColIdx] ?? 'p.product_code';

        $recordsTotal = DB::table('products')
            ->whereNull('deleted_at')
            ->count();
        $recordsFiltered = (clone $q)->select('p.id')->distinct()->count('p.id');

        if ($orderCol === 'total_stock') {
            $q->orderByRaw('total_stock '.$orderDir);
        } else {
            $q->orderBy($orderCol, $orderDir);
        }

        $data = $q->offset($start)->limit($length)->get();

        $rows = $data->map(function($p,$i) use ($start){
            $qty = max((int)($p->total_stock ?? 0), 0);
            $min = (int)($p->stock_minimum ?? 0);

            $isLow = $min > 0 && $qty <= $min;

            if ((int)$p->is_active !== 1) {
                $statusBadge = '<span class="badge bg-secondary">INACTIVE</span>';
            } else {
                if ($qty <= $min) {
                    $statusBadge = '<span class="badge bg-danger">LOW</span>';
                } else {
                    $statusBadge = '<span class="badge bg-success">OK</span>';
                }
            }

            $stockHtml = $isLow
                ? '<span class="text-danger fw-bold">' . number_format($qty, 0, ',', '.') . '</span>'
                : number_format($qty, 0, ',', '.');

            $editBtn = auth()->user()->hasPermission('products.update')
                ? sprintf(
                    '<button class="btn btn-sm btn-icon btn-outline-secondary js-edit"
                        data-id="%1$d"
                        data-product_code="%2$s"
                        data-name="%3$s"
                        data-category_id="%4$s"
                        data-package_id="%5$s"
                        data-supplier_id="%6$s"
                        data-description="%7$s"
                        data-purchasing_price="%8$s"
                        data-selling_price="%9$s"
                        data-stock_minimum="%10$s"
                        data-product_type="%11$s"
                        data-is_active="%12$s"
                        data-standard_cost="%13$s">
                        <i class="bx bx-edit-alt"></i>
                    </button>',
                    $p->id,
                    e($p->product_code),
                    e($p->name),
                    $p->category_id ?? '',
                    $p->package_id ?? '',
                    $p->supplier_id ?? '',
                    e($p->description ?? ''),
                    (int)$p->purchasing_price,
                    (int)$p->selling_price,
                    e($p->stock_minimum ?? ''),
                    $p->product_type ?? 'normal',
                    $p->is_active ?? 1,
                    $p->standard_cost ?? ''
                )
                : '';

            $deleteBtn = auth()->user()->hasPermission('products.delete')
                ? sprintf(
                    '<button class="btn btn-sm btn-icon btn-outline-danger js-del" data-id="%d">
                        <i class="bx bx-trash"></i>
                    </button>',
                    $p->id
                )
                : '';

            $actions = '<div class="d-flex gap-1">'.$editBtn.$deleteBtn.'</div>';

            return [
                'rownum'           => $start + $i + 1,
                'product_code'     => e($p->product_code),
                'name'             => e($p->name),
                'category'         => e($p->category_name ?? '-'),
                'package'          => e($p->package_name ?? '-'),
                'supplier'         => e($p->supplier_name ?? '-'),
                'product_type'     => ucfirst($p->product_type),
                'description'      => e(Str::limit($p->description ?? '-', 80)),
                'stock'            => $stockHtml,
                'min_stock'        => number_format($min, 0, ',', '.'),
                'purchasing_price' => 'Rp'.number_format((int)$p->purchasing_price, 0, ',', '.'),
                'standard_cost'    => 'Rp'.number_format((int)$p->standard_cost, 0, ',', '.'),
                'selling_price'    => 'Rp'.number_format((int)$p->selling_price, 0, ',', '.'),
                'status'           => $statusBadge,
                'actions'          => $actions,
            ];

        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    } catch (\Throwable $e) {
        Log::error('DT Products error: '.$e->getMessage());
        return response()->json([
            'draw'            => (int)$request->input('draw',1),
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
            'error'           => $e->getMessage(),
        ], 500);
    }
}


    public function store(Request $request)
    {
        $this->ensureProductPermission('products.create');

        $code = strtoupper(trim((string)$request->input('product_code','')));
        if ($code === '') $code = $this->generateNextCode();
        $request->merge(['product_code' => $code]);

        $data = $request->validate([
            'product_code'     => ['required','max:50','unique:products,product_code'],
            'name'             => ['required','max:150'],
            'category_id'      => ['required','exists:categories,id'],
            'package_id'       => ['required','exists:packages,id'],
            'supplier_id'      => ['required','exists:suppliers,id'],
            'description'      => ['nullable','string'],
            'purchasing_price' => ['required','numeric','min:0'],
            'selling_price'    => ['required','numeric','min:0'],
            'standard_cost'    => ['nullable','numeric','min:0'],
            'product_type'     => ['required','in:material,BOM,normal'],
            'is_active'        => ['nullable','boolean'],
            'stock_minimum' => ['nullable','integer','min:0'],
            'target_all_warehouses' => ['nullable', 'boolean'],
            'target_warehouse_ids' => ['nullable', 'array'],
            'target_warehouse_ids.*' => ['integer', 'exists:warehouses,id'],

        ]);

        if (($data['product_type'] ?? null) === 'BOM' && ! $request->boolean('target_all_warehouses')) {
            $request->validate([
                'target_warehouse_ids' => ['required', 'array', 'min:1'],
            ]);
        }

        $data['is_active'] = $request->boolean('is_active', true);
        unset($data['target_all_warehouses'], $data['target_warehouse_ids']);

        $product = Product::create($data);

        if ($product->product_type === 'BOM') {
            $this->createBomWarehouseStocks(
                $product->id,
                $request->boolean('target_all_warehouses'),
                $request->input('target_warehouse_ids', [])
            );
        }

        return response()->json(['success' => 'Product created successfully.']);
    }

    public function update(Request $request, Product $product)
    {
        $this->ensureProductPermission('products.update');

        $code = strtoupper(trim((string)$request->input('product_code','')));
        if ($code === '') $code = $product->product_code;
        $request->merge(['product_code' => $code]);

        $data = $request->validate([
            'product_code'     => ['required','max:50', Rule::unique('products','product_code')->ignore($product->id)],
            'name'             => ['required','max:150'],
            'category_id'      => ['required','exists:categories,id'],
            'package_id'       => ['nullable','exists:packages,id'],
            'supplier_id'      => ['nullable','exists:suppliers,id'],
            'description'      => ['nullable','string'],
            'purchasing_price' => ['required','numeric','min:0'],
            'selling_price'    => ['required','numeric','min:0'],
            'standard_cost'    => ['nullable','numeric','min:0'],
            'product_type'     => ['required','in:material,BOM,normal'],
            'is_active'        => ['nullable','boolean'],
            'stock_minimum' => ['nullable','integer','min:0'],
            'target_all_warehouses' => ['nullable', 'boolean'],
            'target_warehouse_ids' => ['nullable', 'array'],
            'target_warehouse_ids.*' => ['integer', 'exists:warehouses,id'],

        ]);

        // Kunci harga beli & jual saat EDIT
        $priceChanged = (
            (int)$data['purchasing_price'] !== (int)$product->purchasing_price ||
            (int)$data['selling_price']    !== (int)$product->selling_price
        );

        if ($priceChanged) {
            return response()->json([
                'error' => 'Harga beli & harga jual tidak bisa diubah dari sini. Silakan gunakan menu Adjustment untuk mengubah harga.',
            ], 422);
        }

        if (($data['product_type'] ?? null) === 'BOM' && ! $request->boolean('target_all_warehouses')) {
            $request->validate([
                'target_warehouse_ids' => ['required', 'array', 'min:1'],
            ]);
        }

        $targetAllWarehouses = $request->boolean('target_all_warehouses');
        $targetWarehouseIds = $request->input('target_warehouse_ids', []);

        unset($data['target_all_warehouses'], $data['target_warehouse_ids']);

        $data['is_active'] = $request->boolean('is_active');
        $product->update($data);

        $warning = null;

        if ($product->product_type === 'BOM') {
            $warning = $this->syncBomWarehouseStocks(
                $product->id,
                $targetAllWarehouses,
                $targetWarehouseIds
            );
        }

        return response()->json([
            'success' => 'Product updated successfully.',
            'warning' => $warning,
        ]);
    }

    public function destroy(Product $product)
    {
        $this->ensureProductPermission('products.delete');

        if (Schema::hasTable('stock_levels')) {
            $totalStock = (int) DB::table('stock_levels')
                ->where('product_id', $product->id)
                ->sum('quantity');

            if ($totalStock > 0) {
                return response()->json([
                    'message' => "Product tidak bisa dihapus karena stoknya masih ada ({$totalStock}).",
                ], 422);
            }
        }

        if ($product->usedInBomItems()->exists()) {
            return response()->json([
                'message' => 'Product tidak bisa dihapus karena masih dipakai sebagai material di BOM.',
            ], 422);
        }

        if ($product->bom()->exists()) {
            return response()->json([
                'message' => 'Product tidak bisa dihapus karena masih terdaftar sebagai finished product di BOM.',
            ], 422);
        }

        if ($product->productionTransactions()->exists()) {
            return response()->json([
                'message' => 'Product tidak bisa dihapus karena sudah punya histori production.',
            ], 422);
        }

        $product->delete();
        return response()->json(['success' => 'Product deleted successfully.']);
    }

    public function nextCode()
    {
        $this->ensureProductPermission('products.create');

        return response()->json(['next_code' => $this->generateNextCode()]);
    }

    public function warehouseTargets(Product $product)
    {
        $this->ensureProductPermission('products.update');

        $rows = DB::table('stock_levels as sl')
            ->join('warehouses as w', 'w.id', '=', 'sl.owner_id')
            ->where('sl.owner_type', 'warehouse')
            ->where('sl.product_id', $product->id)
            ->select('w.id', 'w.warehouse_name', 'sl.quantity')
            ->orderBy('w.warehouse_name')
            ->get();

        $allWarehouseIds = Warehouse::query()->pluck('id')->map(fn($id) => (int) $id)->all();
        $selectedWarehouseIds = $rows->pluck('id')->map(fn($id) => (int) $id)->all();

        return response()->json([
            'selected_warehouse_ids' => $selectedWarehouseIds,
            'all_selected' => !empty($allWarehouseIds) && empty(array_diff($allWarehouseIds, $selectedWarehouseIds)),
            'locked_warehouse_ids' => $rows->where('quantity', '>', 0)->pluck('id')->map(fn($id) => (int) $id)->all(),
            'rows' => $rows,
        ]);
    }

    private function generateNextCode(): string
    {
        $prefix = $this->codePrefix;
        $latest = Product::withTrashed()
    ->where('product_code','like',$prefix.'%')

            ->orderByRaw(
                'CAST(SUBSTRING(product_code, '.(strlen($prefix)+1).') AS UNSIGNED) DESC'
            )
            ->value('product_code');

        $num = 0;
        if ($latest && preg_match('/^'.preg_quote($prefix,'/').'(\d+)$/i',$latest,$m)) {
            $num = (int)$m[1];
        }

        return $prefix . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }

    private function createBomWarehouseStocks(int $productId, bool $allWarehouses, array $warehouseIds): void
    {
        if (! Schema::hasTable('stock_levels')) {
            return;
        }

        $targetWarehouseIds = $allWarehouses
            ? Warehouse::query()->pluck('id')->all()
            : array_values(array_unique(array_map('intval', $warehouseIds)));

        foreach ($targetWarehouseIds as $warehouseId) {
            $exists = DB::table('stock_levels')
                ->where('owner_type', 'warehouse')
                ->where('owner_id', $warehouseId)
                ->where('product_id', $productId)
                ->exists();

            if ($exists) {
                DB::table('stock_levels')
                    ->where('owner_type', 'warehouse')
                    ->where('owner_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->update([
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('stock_levels')->insert([
                'owner_type' => 'warehouse',
                'owner_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function syncBomWarehouseStocks(int $productId, bool $allWarehouses, array $warehouseIds): ?string
    {
        if (! Schema::hasTable('stock_levels')) {
            return null;
        }

        $targetWarehouseIds = $allWarehouses
            ? Warehouse::query()->pluck('id')->map(fn($id) => (int) $id)->all()
            : array_values(array_unique(array_map('intval', $warehouseIds)));

        $this->createBomWarehouseStocks($productId, false, $targetWarehouseIds);

        $existingRows = DB::table('stock_levels as sl')
            ->join('warehouses as w', 'w.id', '=', 'sl.owner_id')
            ->where('sl.owner_type', 'warehouse')
            ->where('sl.product_id', $productId)
            ->select('sl.id', 'sl.owner_id', 'sl.quantity', 'w.warehouse_name')
            ->get();

        $blockedWarehouses = [];

        foreach ($existingRows as $row) {
            if (in_array((int) $row->owner_id, $targetWarehouseIds, true)) {
                continue;
            }

            if ((int) $row->quantity > 0) {
                $blockedWarehouses[] = $row->warehouse_name;
                continue;
            }

            DB::table('stock_levels')
                ->where('id', $row->id)
                ->delete();
        }

        if (empty($blockedWarehouses)) {
            return null;
        }

        return 'Warehouse berikut tidak dilepas karena stoknya masih ada: ' . implode(', ', $blockedWarehouses);
    }
}
