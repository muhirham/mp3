<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Supplier;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        return view('dashboard.indexAdmin', [
            'total_products' => Product::count(),
            'total_warehouses' => Warehouse::count(),
            'total_users' => User::count(),
            'total_suppliers' => Supplier::count(),
        ]);
    }
}
