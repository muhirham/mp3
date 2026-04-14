<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HandoverUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $salesId;
    public $handoverId;
    public $updateType;

    /**
     * Create a new event instance.
     */
    public function __construct($salesId, $handoverId = null, $updateType = 'general')
    {
        $this->salesId = $salesId;
        $this->handoverId = $handoverId;
        $this->updateType = $updateType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Tetap pake sales-channel biar nggak banyak channel yang dibuka
        return [
            new Channel('sales-channel'),
        ];
    }

    /**
     * Nama event yang akan didengar di JS
     */
    public function broadcastAs(): string
    {
        return 'handover-updated';
    }
}
