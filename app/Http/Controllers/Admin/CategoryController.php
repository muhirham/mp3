<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        // kode awal buat prefll di form create
        $nextCode = Category::nextCode();

        return view('admin.masterdata.categories', compact('nextCode'));
    }

    public function datatable(Request $r)
    {
        $columns = ['id','category_code','category_name','description','updated_at'];

        $draw        = (int) $r->input('draw', 1);
        $start       = (int) $r->input('start', 0);
        $length      = (int) $r->input('length', 10);
        $orderColIdx = (int) $r->input('order.0.column', 0);
        $orderDir    = $r->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $orderCol    = $columns[$orderColIdx] ?? 'id';
        $search      = trim((string) $r->input('search.value', ''));

        $base = Category::query()->select($columns);

        $recordsTotal = (clone $base)->count();

        if ($search !== '') {
            $base->where(function($q) use ($search){
                $q->where('category_code','like',"%{$search}%")
                  ->orWhere('category_name','like',"%{$search}%")
                  ->orWhere('description','like',"%{$search}%");
            });
        }

        $recordsFiltered = (clone $base)->count();

        $rows = $base->orderBy($orderCol, $orderDir)
            ->skip($start)->take($length)->get()
            ->map(function ($c) {
                return [
                    'id'            => $c->id,
                    'category_code' => e($c->category_code),
                    'category_name' => e($c->category_name),
                    'description'   => e($c->description ?? '-'),
                    'updated_at'    => optional($c->updated_at)->format('Y-m-d H:i'),
                    'actions'       => '
                        <div class="text-end">
                          <button class="btn btn-sm btn-outline-secondary"
                            onclick="openEdit('.$c->id.',\''.e($c->category_code).'\',\''.e($c->category_name).'\',\''.e($c->description ?? '').'\')">
                            <i class="bx bx-edit"></i>
                          </button>
                          <button class="btn btn-sm btn-outline-danger" onclick="delCategory('.$c->id.')">
                            <i class="bx bx-trash"></i>
                          </button>
                        </div>',
                ];
            });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            // boleh kosong → auto generate di bawah
            'category_code' => ['nullable','max:30','alpha_dash', Rule::unique('categories','category_code')],
            'category_name' => ['required','max:150'],
            'description'   => ['nullable','max:255'],
        ]);

        if (empty($data['category_code'])) {
            $data['category_code'] = Category::nextCode();
        }

        Category::create($data);
        return response()->json(['success' => 'Category created']);
    }

    public function update(Request $r, Category $category)
    {
        $data = $r->validate([
            'category_code' => [
                'required','max:30','alpha_dash',
                Rule::unique('categories','category_code')->ignore($category->id)
            ],
            'category_name' => ['required','max:150'],
            'description'   => ['nullable','max:255'],
        ]);

        $category->update($data);
        return response()->json(['success' => 'Category updated']);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['success' => 'Category deleted']);
    }
}
