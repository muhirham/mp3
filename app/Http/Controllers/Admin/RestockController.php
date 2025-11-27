<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Support\Facades\Auth;

class RestockController extends Controller
{
    public function index()
    {
        $warehouse_id = Auth::user()->warehouse_id;
        $stock = StockLevel::where('owner_type', 'warehouse')
            ->where('owner_id', $warehouse_id)
            ->with('product')
            ->get();

        return view('admin.operations.stock', compact('stock'));
    }
}
