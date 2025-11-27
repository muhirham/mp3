<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\SalesReport;
use App\Models\SalesReturn;
use App\Models\StockLevel;
use Illuminate\Support\Facades\Auth;

class SalesController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        $sales_reports = SalesReport::where('sales_id', $user->id)->count();
        $returns = SalesReturn::where('sales_id', $user->id)->count();
        $stock = StockLevel::where('owner_type', 'sales')
            ->where('owner_id', $user->id)
            ->sum('quantity');

        return view('dashboard.indexSales', compact('sales_reports', 'returns', 'stock'));
    }

    public function report()
    {
        $user = Auth::user();
        $reports = SalesReport::where('sales_id', $user->id)->latest()->paginate(10);
        return view('sales.reports.index', compact('reports'));
    }

    public function return()
    {
        $user = Auth::user();
        $returns = SalesReturn::where('sales_id', $user->id)->latest()->paginate(10);
        return view('sales.returns.index', compact('returns'));
    }
}
