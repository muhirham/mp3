<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Product;
use App\Models\BomTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class BomController extends Controller
{
    protected string $codePrefix = 'BOM-';
    public function index()
    {
        // Subquery stock pusat
        $stockSub = DB::table('stock_levels')
            ->selectRaw('product_id, SUM(quantity) as total_stock')
            ->where('owner_type', 'pusat')
            ->groupBy('product_id');

        $products = Product::leftJoinSub($stockSub, 'st', 'st.product_id', '=', 'products.id')
            ->where('product_type', 'BOM')
             ->where('products.is_active', 1) // 🔥 TAMBAH INI
            ->select(
                'products.id',
                'products.name',
                'products.stock_minimum',
                DB::raw('COALESCE(st.total_stock,0) as stock')
            )
            ->orderBy('products.name')
            ->get();

        $materials = Product::leftJoinSub($stockSub, 'st', 'st.product_id', '=', 'products.id')
            ->whereIn('product_type', ['material','BOM'])
             ->where('products.is_active', 1) // 🔥 TAMBAH INI
            ->select(
                'products.id',
                'products.name',
                'products.standard_cost', // 🔥 TAMBAH INI
                'products.stock_minimum',
                DB::raw('COALESCE(st.total_stock,0) as stock')
            )
            ->orderBy('products.name')
            ->get();


        $nextCode = $this->generateNextCode();

        return view('admin.operations.bom', compact('products','materials','nextCode'));
    }

    public function datatable(Request $r)
    {
        try {

            $draw   = (int)$r->input('draw',1);
            $start  = (int)$r->input('start',0);
            $length = (int)$r->input('length',10);
            $search = trim((string)$r->input('search.value',''));

            $base = DB::table('boms as b')
                ->join('products as p','p.id','=','b.product_id')
                ->leftJoin('users as uc','uc.id','=','b.created_by')
                ->leftJoin('users as uu','uu.id','=','b.updated_by')
                ->select([
                    'b.id',
                    'b.bom_code',
                    'p.name as product_name',
                    'b.version',
                    'b.is_active',
                    'b.created_at',
                    'b.updated_at',
                    'uc.name as creator_name',
                    'uu.name as updater_name',
                ]);

            if ($search !== '') {
                $like = "%{$search}%";
                $base->where(function($q) use ($like){
                    $q->where('b.bom_code','like',$like)
                    ->orWhere('p.name','like',$like);
                });
            }

            $recordsTotal    = DB::table('boms')->count();
            $recordsFiltered = (clone $base)->count();

            $data = $base
                ->orderBy('b.created_at','desc')
                ->skip($start)
                ->take($length)
                ->get();

                $rows = $data->map(function($r,$i) use ($start){

                    $createdBlock = '
                        <div>
                            <div>'.e($r->creator_name ?? '-').'</div>
                            <small class="text-muted">'.date('d-m-Y H:i', strtotime($r->created_at)).'</small>
                        </div>
                    ';

                    $updatedBlock = '
                        <div>
                            <div>'.e($r->updater_name ?? '-').'</div>
                            <small class="text-muted">'.date('d-m-Y H:i', strtotime($r->updated_at)).'</small>
                        </div>
                    ';

                    return [
                        'rownum' => $start + $i + 1,
                        'bom_code' => e($r->bom_code),
                        'product_name' => e($r->product_name),
                        'version' => $r->version,
                        'status' => $r->is_active
                            ? '<span class="badge bg-success">ACTIVE</span>'
                            : '<span class="badge bg-secondary">INACTIVE</span>',
                        'created_block' => $createdBlock,
                        'updated_block' => $updatedBlock,
                        'actions' => '
                            <button class="btn btn-sm btn-info js-detail" data-id="'.$r->id.'">Detail</button>
                            <button class="btn btn-sm btn-outline-danger js-del" data-id="'.$r->id.'">Delete</button>
                            <button class="btn btn-sm btn-success js-produce" data-id="'.$r->id.'">Produce</button>
                        '
                    ];
                });

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data'            => $rows,
            ]);

        } catch (\Throwable $e) {

            Log::error('BOM DT error: '.$e->getMessage());

            return response()->json([
                'draw' => 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }
    }

    public function store(Request $r)
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'User tidak login');
        }

        $r->validate([
            'bom_code' => ['required','unique:boms,bom_code'],
            'product_id' => ['required_if:product_mode,existing','nullable','exists:products,id'],
            'materials' => ['required','array','min:1'],
            'materials.*' => ['required','exists:products,id'],
            'quantities' => ['required','array'],
            'quantities.*' => ['required','numeric','min:0.01'],
        ]);

        return DB::transaction(function() use ($r, $user){

            if ($r->product_mode === 'new') {
                $product = Product::create([
                    'product_code'     => $r->new_product_code,
                    'name'             => $r->new_product_name,
                    'description'      => $r->new_description,
                    'product_type'     => 'BOM',
                    'selling_price'    => 0,
                    'purchasing_price' => 0,
                ]);

                $productId = $product->id;
            } else {
                $productId = $r->product_id;
            }

            $bom = Bom::create([
                'bom_code'   => $r->bom_code,
                'product_id' => $productId,
                'version'    => $r->version ?? 1,
                'output_qty' => 1,
                'is_active'  => $r->is_active ?? 1,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($r->materials as $i => $materialId) {
                $bom->items()->create([
                    'material_id' => $materialId,
                    'quantity'    => $r->quantities[$i],
                ]);
            }

            return response()->json([
                'success'=>'BOM created successfully',
                'id' => $bom->id
            ]);            
        });
    }

public function showPage(Bom $bom)
{
    $bom->load('items.material');

    return view('admin.operations.bom-show', compact('bom'));
}


    public function edit(Bom $bom)
    {
        $bom->load('items');

        return response()->json([
            'id'        => $bom->id,
            'bom_code'  => $bom->bom_code,
            'version'   => $bom->version,
            'is_active' => $bom->is_active,
            'product_id'=> $bom->product_id,
            'items'     => $bom->items,
        ]);
    }


    public function update(Request $r, Bom $bom)
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'User tidak login');
        }

        $r->validate([
            'bom_code' => ['required','unique:boms,bom_code,' . $bom->id],
            'version'  => ['nullable','integer'],
            'is_active'=> ['nullable','boolean'],

            'materials' => ['required','array','min:1'],
            'materials.*' => ['required','exists:products,id'],

            'quantities' => ['required','array'],
            'quantities.*' => ['required','numeric','min:0.01'],
        ]);

        return DB::transaction(function() use ($r, $bom, $user){

            // 🔥 Update header
            $bom->update([
                'bom_code'   => $r->bom_code,
                'version'    => $r->version ?? $bom->version,
                'is_active'  => $r->is_active ?? $bom->is_active,
                'updated_by' => $user->id,
            ]);

            // 🔥 Hapus item lama
            $bom->items()->delete();

            // 🔥 Insert item baru
            foreach ($r->materials as $i => $materialId) {
                $bom->items()->create([
                    'material_id' => $materialId,
                    'quantity'    => $r->quantities[$i],
                ]);
            }

            return response()->json(['success' => 'BOM updated successfully']);
        });
    }


    public function destroy(Bom $bom)
    {
        $bom->delete();
        return response()->json(['success'=>'BOM deleted']);
    }

    public function nextCode()
    {
        return response()->json([
            'next_code' => $this->generateNextCode()
        ]);
    }

    private function generateNextCode(): string
    {
        $prefix = $this->codePrefix;

        $latest = Bom::where('bom_code', 'like', $prefix.'%')
            ->orderBy('bom_code', 'desc')
            ->value('bom_code');

        $num = 0;

        if ($latest && preg_match('/^'.preg_quote($prefix,'/').'(\d+)$/', $latest, $m)) {
            $num = (int) $m[1];
        }

        return $prefix . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }

    private function resolveOwner()
    {
        $user = auth()->user();

        if ($user->warehouse_id) {
            return [
                'type' => 'warehouse',
                'id'   => $user->warehouse_id
            ];
        }

        return [
            'type' => 'pusat',
            'id'   => 0
        ];
    }

public function produce(Request $r, Bom $bom)
{
    try {

        $user = auth()->user();

        if (!$user) {
            abort(403, 'User tidak login');
        }

        $bom->load('items.material');

        $r->validate([
            'production_qty' => ['required', 'integer', 'min:1']
        ]);

        return DB::transaction(function () use ($r, $bom, $user) {

            $owner = $this->resolveOwner();
            $batch = (int) $r->production_qty;
            $totalOutput = $bom->output_qty * $batch;

            $totalCost = 0;

            /* =====================================================
                1. LOCK & VALIDATE MATERIAL STOCK
            ===================================================== */

            foreach ($bom->items as $item) {

                $qtyUsed = $item->quantity * $batch;

                $stock = DB::table('stock_levels')
                    ->where('owner_type', $owner['type'])
                    ->where('owner_id', $owner['id'])
                    ->where('product_id', $item->material_id)
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantity < $qtyUsed) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock {$item->material->name} tidak cukup"
                    ]);
                }
            }

            /* =====================================================
                2. CREATE TRANSACTION HEADER
            ===================================================== */

            $transaction = BomTransaction::create([
                'bom_id'         => $bom->id,
                'product_id'     => $bom->product_id,
                'production_qty' => $batch,
                'total_cost'     => 0,
                'user_id'        => $user->id,
            ]);

            /* =====================================================
                3. DEDUCT MATERIAL STOCK
            ===================================================== */

            foreach ($bom->items as $item) {

                $qtyUsed = $item->quantity * $batch;

                DB::table('stock_levels')
                    ->where('owner_type', $owner['type'])
                    ->where('owner_id', $owner['id'])
                    ->where('product_id', $item->material_id)
                    ->decrement('quantity', $qtyUsed);

                $cost      = $item->material->standard_cost ?? 0;
                $lineTotal = $cost * $qtyUsed;

                $transaction->items()->create([
                    'material_id'  => $item->material_id,
                    'qty_used'     => $qtyUsed,
                    'cost_per_unit'=> $cost,
                    'total_cost'   => $lineTotal,
                ]);

                $totalCost += $lineTotal;
            }

            /* =====================================================
                4. ADD FINISHED PRODUCT STOCK (SAFE VERSION)
            ===================================================== */

            $existingFinished = DB::table('stock_levels')
                ->where('owner_type', $owner['type'])
                ->where('owner_id', $owner['id'])
                ->where('product_id', $bom->product_id)
                ->lockForUpdate()
                ->first();

            if ($existingFinished) {

                DB::table('stock_levels')
                    ->where('id', $existingFinished->id)
                    ->update([
                        'quantity'   => $existingFinished->quantity + $totalOutput,
                        'updated_at' => now(),
                    ]);

            } else {

                DB::table('stock_levels')->insert([
                    'owner_type' => $owner['type'],
                    'owner_id'   => $owner['id'],
                    'product_id' => $bom->product_id,
                    'quantity'   => $totalOutput,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            /* =====================================================
                5. UPDATE TOTAL COST
            ===================================================== */

            $transaction->update([
                'total_cost' => $totalCost
            ]);

            return response()->json([
                'success' => 'Production executed successfully'
            ]);
        });

    } catch (ValidationException $e) {

        return response()->json([
            'message' => $e->getMessage(),
            'errors'  => $e->errors()
        ], 422);

    } catch (\Throwable $e) {

        Log::error('Produce error: ' . $e->getMessage());

        return response()->json([
            'message' => 'Terjadi kesalahan saat produksi'
        ], 500);
    }
}

}
