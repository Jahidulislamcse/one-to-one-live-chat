<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Chat;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;

    public function __construct(Chat $chat)
    {
        $this->chat = $chat->load('sender');
    }

    public function broadcastOn()
    {
        // Broadcast to private channels for sender and receiver
        return [
            new PrivateChannel('chat.' . $this->chat->sender_id),
            new PrivateChannel('chat.' . $this->chat->receiver_id),
        ];
    }
}
