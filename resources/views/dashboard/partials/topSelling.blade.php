<div class="border-bottom pb-2 mb-2">

    <div class="small text-muted">
        Total Revenue <span class="fw-bold">({{ $label }})</span>
    </div>

    <div class="fw-bold fs-6">
        Rp {{ number_format($totalTopRevenue, 0, ',', '.') }}
    </div>

</div>

@forelse($topSellingProducts as $index => $item)
    <div class="d-flex justify-content-between align-items-center py-1">

        <div class="text-truncate" style="max-width: 65%; font-size:10px;">

            <span class="fw-semibold">
                {{ $index + 1 }}.
            </span>

            {{ $item->name }}

        </div>

        <div class="text-end text-nowrap" style="font-size:9px;">

            Rp {{ number_format($item->sold_amount, 0, ',', '.') }}

        </div>

    </div>

@empty

    <div class="text-muted small">
        No best selling products found
    </div>
@endforelse
