<div class="card-body d-flex flex-wrap gap-2">

    {{-- APPROVE + REJECT (PADANG) --}}
    @if ($canApproveDestination)
        <button class="btn btn-success btn-approve-transfer"
            data-action="{{ route('warehouse-transfer-forms.approve.destination', $transfer->id) }}">
            <i class="bx bx-check-circle"></i> Approve
        </button>

        <button class="btn btn-outline-danger btn-reject-transfer"
            data-action="{{ route('warehouse-transfer-forms.reject.destination', $transfer->id) }}">
            <i class="bx bx-x-circle"></i> Reject
        </button>
    @endif


    {{-- PRINT DELIVERY NOTE --}}
    @if ($canPrintSJ)
        <button id="btnPrintSJ" class="btn btn-outline-primary">
            <i class="bx bx-printer"></i> Delivery Note
        </button>
    @endif

    {{-- GR --}}
    @if ($canGrSource)
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlGRTransfer">
            <i class="bx bx-download"></i> Goods Received
        </button>
    @endif

</div>
