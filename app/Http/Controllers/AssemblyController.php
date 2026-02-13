<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\AssemblyResult;
use App\Models\AssemblyTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Category;


class AssemblyController extends Controller
{

public function index()
{
    // Ambil ID category SALDO
    $saldoCategory = Category::where('category_code', 'CAT-SLD')->first();
        // Ambil ID category KPK
    $kpkCategory   = Category::where('category_code', 'CAT-KPK')->first();


    $saldoProducts = Product::with(['stockLevels' => function ($q) {
            $q->where('owner_type', 'pusat');
        }])
        ->where('category_id', $saldoCategory->id ?? 0)
        ->get();

    $kpkProducts = Product::with(['stockLevels' => function ($q) {
            $q->where('owner_type', 'pusat');
        }])
        ->where('category_id', $kpkCategory->id ?? 0)
        ->get();

    $transactions = AssemblyTransaction::with('user')
        ->latest()
        ->paginate(10);

    return view('admin.operations.assembly', compact(
        'saldoProducts',
        'kpkProducts',
        'transactions'
    ));
}

    public function create()
    {
        $saldoProducts = \App\Models\Product::whereHas('stockLevels', function ($q) {
            $q->where('owner_type', 'pusat');
        })->get();

        $kpkProducts = $saldoProducts; // sementara semua product bisa dipilih

        $results = \App\Models\AssemblyResult::all();

        return view('assembly.create', compact('saldoProducts','kpkProducts','results'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'saldo_id'       => 'required|exists:products,id',
            'kpk_id'         => 'required|exists:products,id',
            'qty'            => 'required|integer|min:1',
            'saldo_per_unit' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request) {

            $saldoStock = StockLevel::where('product_id', $request->saldo_id)
                ->where('owner_type', 'pusat')
                ->lockForUpdate()
                ->firstOrFail();

            $kpkStock = StockLevel::where('product_id', $request->kpk_id)
                ->where('owner_type', 'pusat')
                ->lockForUpdate()
                ->firstOrFail();

            $qty = $request->qty;
            $saldoPerUnit = $request->saldo_per_unit;
            $saldoUsed = $qty * $saldoPerUnit;

            if ($saldoStock->quantity < $saldoUsed) {
                throw new \Exception('Saldo tidak cukup.');
            }

            if ($kpkStock->quantity < $qty) {
                throw new \Exception('Stock KPK tidak cukup.');
            }

            $saldoBefore = $saldoStock->quantity;

            // Kurangi saldo
            $saldoStock->quantity -= $saldoUsed;
            $saldoStock->save();

            // Kurangi KPK
            $kpkStock->quantity -= $qty;
            $kpkStock->save();

            $saldoAfter = $saldoStock->quantity;

            AssemblyTransaction::create([
                'saldo_id'       => $request->saldo_id,
                'kpk_id'         => $request->kpk_id,
                'qty'            => $qty,
                'saldo_per_unit' => $saldoPerUnit,
                'saldo_used'     => $saldoUsed,
                'saldo_before'   => $saldoBefore,
                'saldo_after'    => $saldoAfter,
                'created_by'     => auth()->id(),
            ]);
        });

        return back()->with('success', 'Assembly berhasil diproses.');
    }


}

