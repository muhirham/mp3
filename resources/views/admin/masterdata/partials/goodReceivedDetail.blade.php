    @php
        // $receipts, $first, $po, $displayItems, $goodByProduct, $damagedByProduct sudah dikirim dari controller

        $totalGood = (int) $receipts->sum('qty_good');
        $totalDamaged = (int) $receipts->sum('qty_damaged');
        $photosAll = $receipts->flatMap(fn($r) => $r->photos ?? collect());

        $goodPhotos = collect();
        $damagedPhotos = collect();

        foreach ($photosAll as $p) {
            $tag = strtolower(trim($p->type ?? ($p->kind ?? ($p->caption ?? ''))));
            $isDamaged = strpos($tag, 'dam') !== false || strpos($tag, 'bad') !== false || strpos($tag, 'rusak') !== false;

            if ($isDamaged) {
                $damagedPhotos->push($p);
            } else {
                $goodPhotos->push($p);
            }
        }

        if ($photosAll->count() > 0 && $goodPhotos->count() === 0 && $damagedPhotos->count() === 0) {
            $goodPhotos = $photosAll;
        }

        $typeLabel = match($first->gr_type) {
            'po' => ['text' => 'PURCHASE ORDER', 'color' => 'primary'],
            'request_stock' => ['text' => 'REQUEST STOCK', 'color' => 'warning'],
            'gr_transfer' => ['text' => 'WAREHOUSE TRANSFER', 'color' => 'info'],
            'gr_return' => ['text' => 'SALES RETURN / DAMAGE', 'color' => 'danger'],
            default => ['text' => 'GOODS RECEIVED', 'color' => 'secondary'],
        };

        $lastReceiveAt = optional($first->received_at)->format('d/m/Y H:i') ?? '-';
        $lastReceiver = $first->receiver->name ?? '-';

        // Source Ref
        $sourceRef = $first->code;
        if($first->gr_type == 'po' && $po) {
            $sourceRef = $po->po_code;
        } elseif($first->gr_type == 'request_stock' && $first->request) {
            $sourceRef = $first->request->code;
        } elseif($first->gr_type == 'gr_transfer' && $first->warehouseTransfer) {
            $sourceRef = $first->warehouseTransfer->code;
        }

        // Hitung Subtotal Berdasarkan Barang Bagus (Actual Payable)
        $subtotal = $displayItems->sum(function($it) use ($goodByProduct) {
             return (int)($goodByProduct[$it->product_id] ?? 0) * (float)($it->unit_price ?? 0);
        });
        $discountTotal = $po ? (float) ($po->discount_total ?? 0) : 0;
        $grandTotal = $subtotal - $discountTotal;

        $notes = $receipts->pluck('notes')->filter()->unique()->implode(' | ');

        $formatRupiah = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    @endphp

    <div class="modal-header border-0 pb-1">
        <h5 class="modal-title fw-bold">
            Goods Received Details
            <span class="badge bg-label-{{ $typeLabel['color'] }} ms-2" style="font-size: 0.7rem;">{{ $typeLabel['text'] }}</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body pt-0" style="font-size: 0.9rem;">
        {{-- HEADER --}}
        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-uppercase small text-muted mb-1">Source / GR Code</div>
                    <div class="fs-5 fw-bold text-primary">{{ $sourceRef }}</div>
                    <div class="small text-muted mt-1">
                        GR Date: <strong>{{ $lastReceiveAt }}</strong>
                    </div>
                </div>
                <div class="text-end small">
                    <div class="fw-bold fs-6">{{ $company->name ?? config('app.name', 'Inventory System') }}</div>
                    <div class="text-muted">{{ $first->warehouse?->warehouse_name ?? 'Central Stock' }}</div>
                    <div>Received by: <strong>{{ $lastReceiver }}</strong></div>
                </div>
            </div>
        </div>

        {{-- INFO PO --}}
        <div class="row mb-2">
            <div class="col-md-7">
                <table class="table table-sm table-borderless mb-0 small">
                    @php
                        $fallbackSupplier = $displayItems->first()?->product?->supplier?->name ?? '-';
                    @endphp
                    <tr>
                        <th class="ps-0" style="width:140px;">Supplier / Entity</th>
                        <td class="ps-0">{{ $first->supplier?->name ?? ($po->supplier?->name ?? $fallbackSupplier) }}</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Total Items</th>
                        <td class="ps-0">{{ $displayItems->count() }} items</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Subtotal</th>
                        <td class="ps-0">{{ $formatRupiah($subtotal) }}</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Total Amount</th>
                        <td class="ps-0 fw-semibold">{{ $formatRupiah($grandTotal) }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-5">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <th class="ps-0" style="width:160px;">Total Qty Target</th>
                        <td class="ps-0">{{ $displayItems->sum('qty_ordered') }}</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Total Qty Received</th>
                        <td class="ps-0">{{ $totalGood + $totalDamaged }}</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Total Qty Good</th>
                        <td class="ps-0 text-success fw-semibold">{{ $totalGood }}</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Total Qty Damaged</th>
                        <td class="ps-0 text-danger fw-semibold">{{ $totalDamaged }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- ITEM TABLE --}}
        <div class="mb-3 overflow-auto">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.82rem;">
                <thead class="table-light">
                    <tr class="text-center text-nowrap align-middle">
                        <th style="width:40px;">No.</th>
                        <th class="text-start">Product Name / Description</th>
                        <th style="width:80px;">Qty Target</th>
                        <th style="width:80px;">Qty Received</th>
                        <th style="width:80px;">Qty Good</th>
                        <th style="width:90px;">Qty Damaged</th>
                        <th style="width:110px;">Unit Price</th>
                        <th style="width:120px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($displayItems as $idx => $item)
                        @php
                            $price = $item->unit_price ?? 0;
                            $itemGood = $goodByProduct[$item->product_id] ?? 0;
                            $subtotalItem = (int) $itemGood * (float) $price;
                            $itemGood = $goodByProduct[$item->product_id] ?? 0;
                            $itemDamaged = $damagedByProduct[$item->product_id] ?? 0;
                        @endphp
                        <tr class="align-middle">
                            <td class="text-center text-muted">{{ $idx + 1 }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $item->product->name ?? '-' }}</div>
                                <div class="extra-small text-muted" style="font-size: 0.75rem;">
                                    {{ $item->product->product_code ?? '' }}
                                </div>
                            </td>
                            <td class="text-center">{{ $item->qty_ordered }}</td>
                            <td class="text-center">{{ $itemGood + $itemDamaged }}</td>
                            <td class="text-center text-success fw-semibold">
                                {{ $itemGood }}
                            </td>
                            <td class="text-center text-danger fw-semibold">
                                {{ $itemDamaged }}
                            </td>
                            <td class="text-end">{{ $formatRupiah($price) }}</td>
                            <td class="text-end fw-bold text-dark">{{ $formatRupiah($subtotalItem) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- NOTES --}}
        <div class="mb-3">
            <div class="fw-semibold mb-1">Receiving Notes</div>
            <div class="border rounded p-2" style="min-height:60px;">
                {{ $notes ?: '-' }}
            </div>
        </div>

        {{-- FOTO --}}
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="fw-semibold mb-2">Good Item Photos</div>
                @if ($goodPhotos->count())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($goodPhotos as $p)
                            <a href="{{ asset('storage/' . $p->path) }}" target="_blank">
                                <img src="{{ asset('storage/' . $p->path) }}" loading="lazy" class="rounded border"
                                    style="width:90px;height:90px;object-fit:cover;">
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No good item photos.</p>
                @endif
            </div>

            <div class="col-md-6 mb-3">
                <div class="fw-semibold text-danger mb-2">Damaged Item Photos</div>
                @if ($damagedPhotos->count())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($damagedPhotos as $p)
                            <a href="{{ asset('storage/' . $p->path) }}" target="_blank">
                                <img src="{{ asset('storage/' . $p->path) }}" loading="lazy"
                                    class="rounded border border-danger"
                                    style="width:90px;height:90px;object-fit:cover;">
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No damaged item photos.</p>
                @endif
            </div>
        </div>

        {{-- TANDA TANGAN --}}
        <br><br><br>
        <div class="row mt-4">
            <div class="col-md-6 text-center">
                <div class="small mb-5">Received by,</div>
                <div style="height:40px;"></div>
                <div class="fw-semibold">{{ $lastReceiver ?: '________________' }}</div>
                <div class="small text-muted">Warehouse / Receiver</div>
            </div>
            <div class="col-md-6 text-center">
                <div class="small mb-5">Submitted by,</div>
                <div style="height:40px;"></div>
                <div class="fw-semibold">________________</div>
                <div class="small text-muted">Supplier / Courier</div>
            </div>
        </div>
    </div>

    <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            Close
        </button>

        @if ($isSuperadmin && $first && in_array($first->gr_type, ['po', 'request_stock']))
            <form method="POST" action="{{ route('good-received.cancel', $first->code) }}"
                class="form-cancel-gr d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bx bx-undo"></i>
                    Cancel GR
                </button>
            </form>
        @endif
    </div>
