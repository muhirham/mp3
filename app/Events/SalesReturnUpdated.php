<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesReturnUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $warehouseId;
    public $salesId;
    public $handoverId;
    public $updateType;
    public $salesName;

    /**
     * @param int    $warehouseId  Warehouse tujuan event
     * @param int    $salesId      Sales yang ngajuin return
     * @param int    $handoverId   HDO yang bersangkutan
     * @param string $updateType   'new_return' | 'status_updated'
     * @param string $salesName    Nama sales (buat notifikasi WH Admin)
     */
    public function __construct($warehouseId, $salesId, $handoverId, $updateType = 'general', $salesName = '')
    {
        $this->warehouseId = $warehouseId;
        $this->salesId     = $salesId;
        $this->handoverId  = $handoverId;
        $this->updateType  = $updateType;
        $this->salesName   = $salesName;
    }

    /**
     * Broadcast ke channel warehouse spesifik
     * Sama seperti pola yang dipake di HDO
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sales-channel'),
        ];
    }

    /**
     * Nama event yang didengar di JS
     */
    public function broadcastAs(): string
    {
        return 'sales-return-updated';
    }
}
