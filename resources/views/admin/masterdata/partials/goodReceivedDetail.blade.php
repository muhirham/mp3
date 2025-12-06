    @php
        $receipts      = $po->restockReceipts->sortBy('id');
        $totalGood     = (int) $receipts->sum('qty_good');
        $totalDamaged  = (int) $receipts->sum('qty_damaged');
        $photosAll     = $receipts->flatMap(fn ($r) => $r->photos ?? collect());

        $goodPhotos    = collect();
        $damagedPhotos = collect();

        foreach ($photosAll as $p) {
            $tag = strtolower(trim($p->kind ?? $p->type ?? $p->status ?? $p->caption ?? ''));

            $isDamaged = (strpos($tag, 'dam') !== false)
                    || (strpos($tag, 'bad') !== false)
                    || (strpos($tag, 'rusak') !== false);

            if ($isDamaged) {
                $damagedPhotos->push($p);
            } else {
                $goodPhotos->push($p);
            }
        }

        if ($photosAll->count() > 0 && $goodPhotos->count() === 0 && $damagedPhotos->count() === 0) {
            $goodPhotos = $photosAll;
        }

        $totalLines = $po->items->count();

        $subtotal      = (float) ($po->subtotal        ?? 0);
        $discountTotal = (float) ($po->discount_total ?? 0);
        $grandTotal    = (float) ($po->grand_total    ?? ($subtotal - $discountTotal));

        $manualTotal = 0;
        foreach ($po->items as $it) {
            $price       = $it->unit_price ?? 0;
            $manualTotal += (int) $it->qty_ordered * (float) $price;
        }
        if ($grandTotal <= 0 && $manualTotal > 0) {
            $grandTotal = $manualTotal;
        }

        $lastReceipt   = $receipts->sortByDesc('received_at')->first();
        $lastReceiveAt = optional($lastReceipt?->received_at)?->format('d/m/Y H:i') ?? '-';
        $lastReceiver  = $lastReceipt?->receiver->name ?? '-';

        // Supplier summary (modal)
        $supFromPo        = optional($po->supplier)->name;
        $itemSuppliers    = $po->items->map(fn ($it) => optional(optional($it->product)->supplier)->name)->filter();
        $receiptSuppliers = $receipts->map(fn ($r) => optional($r->supplier)->name)->filter();

        $supplierNamesModal = collect([$supFromPo])
            ->merge($itemSuppliers)
            ->merge($receiptSuppliers)
            ->filter()
            ->unique()
            ->values();

        if ($supplierNamesModal->isEmpty()) {
            $supplierLabelModal = '-';
        } elseif ($supplierNamesModal->count() === 1) {
            $supplierLabelModal = $supplierNamesModal->first();
        } else {
            $supplierLabelModal = $supplierNamesModal->first() . ' + ' . ($supplierNamesModal->count() - 1) . ' supplier';
        }

        // Warehouse label (modal)
        $warehouseNamesModal = $receipts
            ->map(function ($r) {
                if ($r->warehouse) {
                    return $r->warehouse->warehouse_name
                        ?? $r->warehouse->name
                        ?? 'Warehouse #' . $r->warehouse_id;
                }
                return 'Central Stock';
            })
            ->filter()
            ->unique()
            ->values();

        if ($warehouseNamesModal->isEmpty()) {
            $warehouseLabelModal = '-';
        } elseif ($warehouseNamesModal->count() === 1) {
            $warehouseLabelModal = $warehouseNamesModal->first();
        } else {
            $hasCentralModal = $warehouseNamesModal->contains('Central Stock');
            $otherCountModal = $warehouseNamesModal->count() - 1;

            if ($hasCentralModal) {
                $warehouseLabelModal = 'Central Stock + ' . $otherCountModal . ' wh';
            } else {
                $warehouseLabelModal = $warehouseNamesModal->first() . ' + ' . $otherCountModal . ' wh';
            }
        }

        $notes = $receipts->pluck('notes')->filter()->unique()->implode(' | ');

        $formatRupiah = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    @endphp

    <div class="modal-header border-0 pb-1">
    <h5 class="modal-title fw-bold">
        Tanda Terima Barang (GR) &amp; Detail PO
    </h5>
    <button type="button"
            class="btn-close"
            data-bs-dismiss="modal"
            aria-label="Close"></button>
    </div>

    <div class="modal-body pt-0">
    {{-- HEADER --}}
    <div class="border rounded p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="text-uppercase small text-muted mb-1">PO Code</div>
            <div class="fs-5 fw-bold">{{ $po->po_code }}</div>
            <div class="small text-muted">
            Terakhir diterima: {{ $lastReceiveAt }}
            </div>
        </div>
        <div class="text-end small">
            <div class="fw-bold">{{ $company->name ?? config('app.name', 'Inventory System') }}</div>
            <div>{{ $warehouseLabelModal }}</div>
            <div>Diterima oleh: <strong>{{ $lastReceiver }}</strong></div>
        </div>
        </div>
    </div>

    {{-- INFO PO --}}
    <div class="row mb-2">
        <div class="col-md-7">
        <table class="table table-sm table-borderless mb-0">
            <tr>
            <th class="ps-0" style="width:140px;">Supplier</th>
            <td class="ps-0">{{ $supplierLabelModal }}</td>
            </tr>
            <tr>
            <th class="ps-0">Total Item</th>
            <td class="ps-0">{{ $totalLines }} item</td>
            </tr>
            <tr>
            <th class="ps-0">Subtotal</th>
            <td class="ps-0">{{ $formatRupiah($subtotal) }}</td>
            </tr>
            <tr>
            <th class="ps-0">Discount</th>
            <td class="ps-0">{{ $formatRupiah($discountTotal) }}</td>
            </tr>
            <tr>
            <th class="ps-0">Total Amount</th>
            <td class="ps-0 fw-semibold">{{ $formatRupiah($grandTotal) }}</td>
            </tr>
        </table>
        </div>
        <div class="col-md-5">
        <table class="table table-sm table-borderless mb-0">
            <tr>
            <th class="ps-0" style="width:160px;">Total Qty Order</th>
            <td class="ps-0">{{ $po->items->sum('qty_ordered') }}</td>
            </tr>
            <tr>
            <th class="ps-0">Total Qty Received</th>
            <td class="ps-0">{{ $po->items->sum('qty_received') }}</td>
            </tr>
            <tr>
            <th class="ps-0">Total Qty Good (GR)</th>
            <td class="ps-0 text-success fw-semibold">{{ $totalGood }}</td>
            </tr>
            <tr>
            <th class="ps-0">Total Qty Damaged (GR)</th>
            <td class="ps-0 text-danger fw-semibold">{{ $totalDamaged }}</td>
            </tr>
        </table>
        </div>
    </div>

    {{-- ITEM PO + HARGA --}}
    <div class="mb-3">
        <table class="table table-sm table-bordered mb-0">
        <thead class="table-light">
            <tr class="text-center">
            <th style="width:50px;">No.</th>
            <th>Nama Barang / Deskripsi</th>
            <th style="width:90px;">Qty Order</th>
            <th style="width:90px;">Qty Received</th>
            <th style="width:120px;">Harga Satuan</th>
            <th style="width:130px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $idx => $item)
            @php
                $price        = $item->unit_price ?? 0;
                $subtotalItem = (int) $item->qty_ordered * (float) $price;
            @endphp
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>
                {{ $item->product->name ?? '-' }}<br>
                <small class="text-muted">
                    {{ $item->product->product_code ?? '' }}
                </small>
                </td>
                <td class="text-center">{{ $item->qty_ordered }}</td>
                <td class="text-center">{{ $item->qty_received }}</td>
                <td class="text-end">{{ $formatRupiah($price) }}</td>
                <td class="text-end fw-semibold">{{ $formatRupiah($subtotalItem) }}</td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>

    {{-- NOTES --}}
    <div class="mb-3">
        <div class="fw-semibold mb-1">Catatan Penerimaan</div>
        <div class="border rounded p-2" style="min-height:60px;">
        {{ $notes ?: '-' }}
        </div>
    </div>

    {{-- FOTO --}}
    <div class="row">
        <div class="col-md-6 mb-3">
        <div class="fw-semibold mb-2">Foto Barang Good</div>
        @if($goodPhotos->count())
            <div class="d-flex flex-wrap gap-2">
            @foreach($goodPhotos as $p)
                <a href="{{ asset('storage/'.$p->path) }}" target="_blank">
                <img src="{{ asset('storage/'.$p->path) }}"
                    loading="lazy"
                    class="rounded border"
                    style="width:90px;height:90px;object-fit:cover;">
                </a>
            @endforeach
            </div>
        @else
            <p class="text-muted mb-0">Tidak ada foto barang good.</p>
        @endif
        </div>

        <div class="col-md-6 mb-3">
        <div class="fw-semibold text-danger mb-2">Foto Barang Damaged</div>
        @if($damagedPhotos->count())
            <div class="d-flex flex-wrap gap-2">
            @foreach($damagedPhotos as $p)
                <a href="{{ asset('storage/'.$p->path) }}" target="_blank">
                <img src="{{ asset('storage/'.$p->path) }}"
                    loading="lazy"
                    class="rounded border border-danger"
                    style="width:90px;height:90px;object-fit:cover;">
                </a>
            @endforeach
            </div>
        @else
            <p class="text-muted mb-0">Tidak ada foto barang damaged.</p>
        @endif
        </div>
    </div>

    {{-- TANDA TANGAN --}}
    <br><br><br>
    <div class="row mt-4">
        <div class="col-md-6 text-center">
        <div class="small mb-5">Diterima oleh,</div>
        <div style="height:40px;"></div>
        <div class="fw-semibold">{{ $lastReceiver ?: '________________' }}</div>
        <div class="small text-muted">Warehouse / Penerima</div>
        </div>
        <div class="col-md-6 text-center">
        <div class="small mb-5">Diserahkan oleh,</div>
        <div style="height:40px;"></div>
        <div class="fw-semibold">________________</div>
        <div class="small text-muted">Supplier / Kurir</div>
        </div>
    </div>
    </div>

    <div class="modal-footer border-0">
    <button type="button"
            class="btn btn-outline-secondary"
            data-bs-dismiss="modal">
        Tutup
    </button>

    @if($isSuperadmin && $lastReceipt)
        <form method="POST"
            action="{{ route('good-received.cancel', $lastReceipt) }}"
            class="form-cancel-gr d-inline">
        @csrf
        <button type="submit"
                class="btn btn-outline-danger">
            <i class="bx bx-undo"></i>
            Cancel GR
        </button>
        </form>
    @endif
    </div>
