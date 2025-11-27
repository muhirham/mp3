<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesReport;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index()
    {
        $warehouse_id = Auth::user()->warehouse_id;
        $reports = SalesReport::where('warehouse_id', $warehouse_id)
            ->with('sales')
            ->orderBy('date', 'desc')
            ->paginate(10);

        return view('warehouse.reports.index', compact('reports'));
    }
}