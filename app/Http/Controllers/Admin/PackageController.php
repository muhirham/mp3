<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class PackageController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.packages');
    }

    public function datatable(Request $r)
    {
        try {
            $draw        = (int) $r->input('draw', 1);
            $start       = (int) $r->input('start', 0);
            $length      = (int) $r->input('length', 10);
            $orderColIdx = (int) $r->input('order.0.column', 1);
            $orderDir    = $r->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $search      = trim((string) $r->input('search.value', ''));

            $orderMap = [ 1 => 'package_name' ];
            $orderCol = $orderMap[$orderColIdx] ?? 'package_name';

            $q = Package::query()->select('id','package_name');

            if ($search !== '') {
                $q->where('package_name','like',"%{$search}%");
            }

            $recordsTotal    = Package::count();
            $recordsFiltered = (clone $q)->select('id')->distinct()->count('id');

            $q->orderBy($orderCol, $orderDir);

            $data = $q->offset($start)->limit($length)->get();

            $rows = $data->map(function($p, $i) use ($start){
                $actions = sprintf(
                    '<div class="d-flex gap-1">
                        <button class="btn btn-sm btn-icon btn-outline-secondary js-edit"
                          data-id="%1$d" data-name="%2$s">
                          <i class="bx bx-edit-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-danger js-del" data-id="%1$d">
                          <i class="bx bx-trash"></i>
                        </button>
                    </div>',
                    $p->id, e($p->package_name)
                );

                return [
                    'rownum'  => $start + $i + 1,
                    'name'    => e($p->package_name),
                    'actions' => $actions,
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('DT Packages error: '.$e->getMessage());
            return response()->json([
                'draw' => (int)$r->input('draw',1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'package_name' => ['required','max:150','unique:packages,package_name'],
        ]);
        Package::create($data);
        return response()->json(['success' => 'Satuan ditambahkan.']);
    }

    public function update(Request $r, Package $package)
    {
        $data = $r->validate([
            'package_name' => ['required','max:150', Rule::unique('packages','package_name')->ignore($package->id)],
        ]);
        $package->update($data);
        return response()->json(['success' => 'Satuan diperbarui.']);
    }

    public function destroy(Package $package)
    {
        $package->delete();
        return response()->json(['success' => 'Satuan dihapus.']);
    }
}