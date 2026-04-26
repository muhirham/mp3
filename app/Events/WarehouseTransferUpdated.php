<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WarehouseTransferUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transferId;
    public $sourceWarehouseId;
    public $destinationWarehouseId;
    public $status;
    public $updateType;

    /**
     * Create a new event instance.
     */
    public function __construct($transferId, $sourceWarehouseId, $destinationWarehouseId, $status, $updateType)
    {
        $this->transferId = $transferId;
        $this->sourceWarehouseId = $sourceWarehouseId;
        $this->destinationWarehouseId = $destinationWarehouseId;
        $this->status = $status;
        $this->updateType = $updateType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('warehouse-transfer-channel'),
        ];
    }

    public function broadcastAs()
    {
        return 'warehouse-transfer-updated';
    }
}
