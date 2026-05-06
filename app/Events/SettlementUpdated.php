<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettlementUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public $warehouseId,
        public $settlementId,
        public $type = 'created' // 'created', 'verified'
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Menggunakan public channel 'settlement-channel' agar konsisten dengan style existing
        return [
            new Channel('settlement-channel'),
        ];
    }

    /**
     * Nama event yang akan didengar di frontend
     */
    public function broadcastAs(): string
    {
        return 'settlement-updated';
    }
}
