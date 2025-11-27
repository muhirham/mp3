<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index()
    {
        $nextSupplierCode = $this->generateNextCode();
        return view('admin.masterdata.suppliers', compact('nextSupplierCode'));
    }

    public function datatable(Request $request)
    {
        $orderable = ['id','supplier_code','name','address','phone','note','bank_name','bank_account','updated_at'];

        $draw        = (int) $request->input('draw', 1);
        $start       = (int) $request->input('start', 0);
        $length      = (int) $request->input('length', 10);
        $orderColIdx = (int) $request->input('order.0.column', 0);
        $orderDir    = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $search      = trim((string) $request->input('search.value', ''));

        $orderCol = $orderable[$orderColIdx] ?? 'id';

        $recordsTotal = Supplier::count();

        $base = Supplier::query()->select($orderable);

        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('supplier_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('bank_account', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $base)->count();

        $rows = $base->orderBy($orderCol, $orderDir)
            ->skip($start)->take($length)->get()
            ->map(function ($s, $idx) use ($start) {
                $actions = sprintf(
                    '<div class="d-flex gap-1">
                        <button class="btn btn-sm btn-icon btn-outline-secondary js-edit"
                            data-id="%1$d"
                            data-supplier_code="%2$s"
                            data-name="%3$s"
                            data-address="%4$s"
                            data-phone="%5$s"
                            data-note="%6$s"
                            data-bank_name="%7$s"
                            data-bank_account="%8$s">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-danger js-del" data-id="%1$d">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>',
                    $s->id,
                    e($s->supplier_code),
                    e($s->name),
                    e($s->address ?? ''),
                    e($s->phone ?? ''),
                    e($s->note ?? ''),
                    e($s->bank_name ?? ''),
                    e($s->bank_account ?? '')
                );

                return [
                    'rownum'        => $start + $idx + 1, // NO (i++)
                    'supplier_code' => e($s->supplier_code),
                    'name'          => e($s->name),
                    'address'       => e($s->address ?? '-'),
                    'phone'         => e($s->phone ?? '-'),
                    'note'          => e($s->note ?? '-'),
                    'bank_name'     => e($s->bank_name ?? '-'),
                    'bank_account'  => e($s->bank_account ?? '-'),
                    'actions'       => $actions,
                ];
            });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('supplier_code', '')));
        if ($code === '') $code = $this->generateNextCode();
        $request->merge(['supplier_code' => $code]);

        $request->validate([
            'supplier_code' => ['required','max:50','unique:suppliers,supplier_code'],
            'name'          => ['required','max:150'],
            'address'       => ['nullable'],
            'phone'         => ['nullable','max:50'],
            'note'          => ['nullable'],
            'bank_name'     => ['nullable','max:100'],
            'bank_account'  => ['nullable','max:100'],
        ]);

        Supplier::create($request->only([
            'supplier_code','name','address','phone','note','bank_name','bank_account'
        ]));

        return response()->json([
            'success'   => 'Supplier created successfully.',
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $code = strtoupper(trim((string) $request->input('supplier_code', '')));
        if ($code === '') $code = $supplier->supplier_code;
        $request->merge(['supplier_code' => $code]);

        $request->validate([
            'supplier_code' => ['required','max:50', Rule::unique('suppliers','supplier_code')->ignore($supplier->id)],
            'name'          => ['required','max:150'],
            'address'       => ['nullable'],
            'phone'         => ['nullable','max:50'],
            'note'          => ['nullable'],
            'bank_name'     => ['nullable','max:100'],
            'bank_account'  => ['nullable','max:100'],
        ]);

        $supplier->update($request->only([
            'supplier_code','name','address','phone','note','bank_name','bank_account'
        ]));

        return response()->json(['success' => 'Supplier updated successfully.']);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return response()->json(['success' => 'Supplier deleted successfully.']);
    }

    public function nextCode()
    {
        return response()->json(['next_code' => $this->generateNextCode()]);
    }

    private function generateNextCode(): string
    {
        $latest = Supplier::where('supplier_code', 'like', 'SUP-%')
            ->orderByRaw('CAST(SUBSTRING(supplier_code, 5) AS UNSIGNED) DESC')
            ->value('supplier_code');

        $num = 0;
        if ($latest && preg_match('/^SUP-(\d+)$/i', $latest, $m)) {
            $num = (int) $m[1];
        }
        return 'SUP-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }
}