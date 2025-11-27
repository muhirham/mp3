<?php
// app/Http/Controllers/SalesHandoverController.php
namespace App\Http\Controllers\Warehouse;

        use App\Http\Controllers\Controller;
        use App\Models\SalesHandover;
        use App\Models\SalesHandoverItem;
        use Illuminate\Http\Request;
        use Illuminate\Support\Facades\DB;
        use Illuminate\Support\Facades\Hash;

        class SalesHandoverController extends Controller
        {
        /** Halaman report & list */
        public function index() { 
            return view('wh.sales_report'); 
        }


        public function items(SalesHandover $handover)
                {
                    // batasi akses per gudang (kalau user nempel ke gudang tertentu)
                    $me = auth()->user();
                    if ($me->warehouse_id && $handover->warehouse_id != $me->warehouse_id) {
                        abort(403);
                    }

                    $items = $handover->items()
                        ->with(['product:id,name'])
                        ->get()
                        ->map(function ($x) {
                            return [
                                'product_id'           => $x->product_id,
                                'product_name'         => $x->product->name ?? ('Produk #'.$x->product_id),
                                'qty_dispatched'       => (int) $x->qty_dispatched,
                                'qty_returned_good'    => (int) $x->qty_returned_good,
                                'qty_returned_damaged' => (int) $x->qty_returned_damaged,
                                'qty_sold'             => (int) $x->qty_sold,
                            ];
                        });

                    return response()->json(['items' => $items]);
                }


        /** Pagi: buat handover + geser stok WH -> SALES */
        public function issue(Request $r)
        {
            $me = auth()->user();
            $data = $r->validate([
            'warehouse_id' => ['required','exists:warehouses,id'],
            'sales_id'     => ['required','exists:users,id'],
            'handover_date'=> ['required','date'],
            'items'        => ['required','array','min:1'],
            'items.*.product_id' => ['required','exists:products,id'],
            'items.*.qty'        => ['required','integer','min:1'],
            ]);

            DB::transaction(function() use ($data,$me) {
            $seq = (int) (SalesHandover::max('id') ?? 0) + 1;
            $code = 'HDO-'.now()->format('ymd').'-'.str_pad($seq,4,'0',STR_PAD_LEFT);

            $h = SalesHandover::create([
                'code'=>$code,
                'warehouse_id'=>$data['warehouse_id'],
                'sales_id'=>$data['sales_id'],
                'handover_date'=>$data['handover_date'],
                'status'=>'issued',
                'issued_by'=>$me->id,
            ]);

            foreach ($data['items'] as $it) {
                $pid = (int)$it['product_id'];
                $qty = (int)$it['qty'];

                // lock stok gudang
                $wh = DB::table('stock_levels')
                ->where('owner_type','warehouse')->where('owner_id',$data['warehouse_id'])
                ->where('product_id',$pid)->lockForUpdate()->first();

                if (!$wh || $wh->quantity < $qty) {
                throw new \RuntimeException("Stok gudang kurang untuk product #{$pid}");
                }

                // kurangi gudang
                DB::table('stock_levels')->where('id',$wh->id)
                ->update(['quantity'=>$wh->quantity - $qty, 'updated_at'=>now()]);

                // tambah stok sales
                $sl = DB::table('stock_levels')
                ->where('owner_type','sales')->where('owner_id',$data['sales_id'])
                ->where('product_id',$pid)->lockForUpdate()->first();

                if ($sl) {
                DB::table('stock_levels')->where('id',$sl->id)
                    ->update(['quantity'=>$sl->quantity + $qty, 'updated_at'=>now()]);
                } else {
                DB::table('stock_levels')->insert([
                    'owner_type'=>'sales','owner_id'=>$data['sales_id'],'product_id'=>$pid,
                    'quantity'=>$qty,'created_at'=>now(),'updated_at'=>now()
                ]);
                }

                // movement OUT gudang -> IN sales
                DB::table('stock_movements')->insert([
                'product_id'=>$pid, 'from_type'=>'warehouse','from_id'=>$data['warehouse_id'],
                'to_type'=>'sales','to_id'=>$data['sales_id'], 'quantity'=>$qty,
                'status'=> DB::getSchemaBuilder()->hasColumn('stock_movements','status') ? 'completed' : null,
                'note'=>"Handover {$h->code} (issue)", 'created_at'=>now(),'updated_at'=>now()
                ]);

                SalesHandoverItem::updateOrCreate(
                ['handover_id'=>$h->id, 'product_id'=>$pid],
                ['qty_dispatched'=>$qty]
                );
            }
            });

            return back()->with('success','Handover diterbitkan & stok dipindahkan ke Sales.');
        }

        /** Generate OTP untuk tutup harian (pegang user) */
        public function generateOtp(SalesHandover $handover)
        {
            $this->authorizeAccess($handover); // tulis sendiri kalau perlu
            if ($handover->status !== 'issued') {
            return back()->with('error','Status harus ISSUED untuk generate OTP.');
            }
            $code = (string) random_int(100000, 999999);
            $handover->update([
            'status'=>'waiting_otp',
            'otp_hash'=> Hash::make($code),
            'otp_expires_at'=> now()->addMinutes(15),
            ]);
            // tampilkan ke layar (atau kirim via SMS jika ada gateway)
            return back()->with('success','OTP untuk penutupan: '.$code);
        }

        /** Sore: rekonsiliasi (input sisa & rusak) — butuh OTP valid untuk final */
        public function reconcile(Request $r, SalesHandover $handover)
        {
            $data = $r->validate([
            'otp_code' => ['required','digits:6'],
            'items'    => ['required','array','min:1'],
            'items.*.product_id' => ['required','exists:products,id'],
            'items.*.qty_returned_good'    => ['required','integer','min:0'],
            'items.*.qty_returned_damaged' => ['nullable','integer','min:0'],
            ]);

            // cek OTP
            if ($handover->status !== 'waiting_otp' || !$handover->otp_hash || now()->greaterThan($handover->otp_expires_at)) {
            return back()->with('error','OTP tidak aktif / kadaluarsa.');
            }
            if (!\Illuminate\Support\Facades\Hash::check($data['otp_code'], $handover->otp_hash)) {
            return back()->with('error','OTP salah.');
            }

            DB::transaction(function() use ($handover,$data) {
            foreach ($data['items'] as $it) {
                $pid  = (int)$it['product_id'];
                $good = (int)($it['qty_returned_good'] ?? 0);
                $bad  = (int)($it['qty_returned_damaged'] ?? 0);

                /** @var \App\Models\SalesHandoverItem $row */
                $row = SalesHandoverItem::where('handover_id',$handover->id)->where('product_id',$pid)->lockForUpdate()->first();
                if (!$row) throw new \RuntimeException("Item handover tidak ditemukan (product $pid)");
                $max = max($row->qty_dispatched - ($row->qty_returned_good + $row->qty_returned_damaged + $row->qty_sold), 0);
                if (($good + $bad) > $max) throw new \RuntimeException("Qty kembali melebihi sisa untuk product $pid");

                // 1) balikin stok GOOD ke gudang (sales -> warehouse)
                if ($good > 0) {
                // kurangi stok sales
                $sl = DB::table('stock_levels')->where('owner_type','sales')->where('owner_id',$handover->sales_id)
                        ->where('product_id',$pid)->lockForUpdate()->first();
                if (!$sl || $sl->quantity < $good) throw new \RuntimeException("Stok sales kurang saat return (product $pid)");
                DB::table('stock_levels')->where('id',$sl->id)->update([
                    'quantity'=>$sl->quantity - $good, 'updated_at'=>now()
                ]);

                // tambah stok gudang
                $wh = DB::table('stock_levels')->where('owner_type','warehouse')->where('owner_id',$handover->warehouse_id)
                        ->where('product_id',$pid)->lockForUpdate()->first();
                if ($wh) {
                    DB::table('stock_levels')->where('id',$wh->id)->update([
                    'quantity'=>$wh->quantity + $good, 'updated_at'=>now()
                    ]);
                } else {
                    DB::table('stock_levels')->insert([
                    'owner_type'=>'warehouse','owner_id'=>$handover->warehouse_id,'product_id'=>$pid,
                    'quantity'=>$good,'created_at'=>now(),'updated_at'=>now()
                    ]);
                }

                // movement return sales -> warehouse
                DB::table('stock_movements')->insert([
                    'product_id'=>$pid,'from_type'=>'sales','from_id'=>$handover->sales_id,
                    'to_type'=>'warehouse','to_id'=>$handover->warehouse_id,'quantity'=>$good,
                    'status'=> DB::getSchemaBuilder()->hasColumn('stock_movements','status') ? 'completed' : null,
                    'note'=>"Handover {$handover->code} (return good)",'created_at'=>now(),'updated_at'=>now()
                ]);
                }

                // 2) tandai rusak (opsi: masukkan ke gudang/karantina) – di sini contoh: tetap balik ke gudang
                if ($bad > 0) {
                // kurangi stok sales
                $sl = DB::table('stock_levels')->where('owner_type','sales')->where('owner_id',$handover->sales_id)
                        ->where('product_id',$pid)->lockForUpdate()->first();
                if (!$sl || $sl->quantity < $bad) throw new \RuntimeException("Stok sales kurang saat return damaged (product $pid)");
                DB::table('stock_levels')->where('id',$sl->id)->update(['quantity'=>$sl->quantity - $bad, 'updated_at'=>now()]);

                // movement ke gudang dengan note damaged (qty tidak menambah stok good — tergantung kebijakan)
                DB::table('stock_movements')->insert([
                    'product_id'=>$pid,'from_type'=>'sales','from_id'=>$handover->sales_id,
                    'to_type'=>'warehouse','to_id'=>$handover->warehouse_id,'quantity'=>$bad,
                    'status'=> DB::getSchemaBuilder()->hasColumn('stock_movements','status') ? 'completed' : null,
                    'note'=>"Handover {$handover->code} (return damaged)",'created_at'=>now(),'updated_at'=>now()
                ]);
                }

                // 3) hitung yang terjual = dispatched - total returned - already sold
                $soldNow = ($row->qty_dispatched - ($row->qty_returned_good + $row->qty_returned_damaged) - $row->qty_sold) - ($good + $bad);
                $soldNow = max($soldNow, 0);

                if ($soldNow > 0) {
                // movement sales -> customer (tanpa menambah stok mana pun)
                DB::table('stock_movements')->insert([
                    'product_id'=>$pid,'from_type'=>'sales','from_id'=>$handover->sales_id,
                    'to_type'=>'customer','to_id'=>0,'quantity'=>$soldNow,
                    'status'=> DB::getSchemaBuilder()->hasColumn('stock_movements','status') ? 'completed' : null,
                    'note'=>"Handover {$handover->code} (sold auto from reconciliation)",'created_at'=>now(),'updated_at'=>now()
                ]);
                }

                $row->update([
                'qty_returned_good'    => $row->qty_returned_good + $good,
                'qty_returned_damaged' => $row->qty_returned_damaged + $bad,
                'qty_sold'             => $row->qty_sold + $soldNow,
                ]);
            }

            $handover->update([
                'status'=>'reconciled',
                'reconciled_by'=>auth()->id(),
                'otp_hash'=>null, 'otp_expires_at'=>null
            ]);
            });

            return back()->with('success','Rekonsiliasi selesai & ditutup dengan OTP.');
        }

        /** Datatable report: IN/OUT per sales per hari */
        public function reportDatatable(Request $r)
        {
            $dateFrom = $r->input('from');
            $dateTo   = $r->input('to');

            $q = DB::table('sales_handovers as h')
            ->join('users as u','u.id','=','h.sales_id')
            ->join('sales_handover_items as i','i.handover_id','=','h.id')
            ->selectRaw("
                h.handover_date,
                h.code,
                u.name as sales_name,
                SUM(i.qty_dispatched) as dispatched,
                SUM(i.qty_returned_good) as returned_good,
                SUM(i.qty_returned_damaged) as returned_damaged,
                SUM(i.qty_sold) as sold
            ")
            ->groupBy('h.handover_date','h.code','u.name')
            ->orderBy('h.handover_date','desc')->orderBy('h.code','desc');

            if ($dateFrom) $q->where('h.handover_date','>=',$dateFrom);
            if ($dateTo)   $q->where('h.handover_date','<=',$dateTo);

            return response()->json(['data'=>$q->get()]);
        }

        protected function authorizeAccess(SalesHandover $h) {
            // taruh policy lu sendiri kalau perlu (Admin WH / pemilik warehouse)
        }
        }
