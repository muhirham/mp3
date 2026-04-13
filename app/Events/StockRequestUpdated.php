<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        // Kita nggak perlu bawa data banyak, cukup sinyal "ada yang baru" aja cok
    }

    /**
     * Get the channels the event should broadcast on.
     * Kita pake Public Channel biar gampang buat PoC awal.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sales-channel'),
        ];
    }

    /**
     * Nama event yang bakal didengerin sama Echo di JavaScript.
     */
    public function broadcastAs(): string
    {
        return 'stock-request-updated';
    }
}
