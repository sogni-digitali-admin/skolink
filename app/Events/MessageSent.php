<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Class MessageSent
 *
 * Broadcasts a new chat message to the conversation's private channel
 * (Reverb / pusher protocol). Channel authorization lives in
 * routes/channels.php (conversation.{uuid}, participants only).
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The sent message (conversation relation guaranteed loaded).
     *
     * @var \App\Models\Message
     */
    public $message;

    /**
     * Create a new event instance.
     *
     * @param \App\Models\Message $message
     */
    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing('conversation');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\PrivateChannel
     */
    public function broadcastOn()
    {
        return new PrivateChannel('conversation.'.$this->message->conversation->uuid);
    }

    /**
     * The event name the PWA listens for.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'MessageSent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'id'        => $this->message->id,
            'uuid'      => $this->message->conversation->uuid,
            'body'      => $this->message->body,
            'senderId'  => $this->message->user_id,
            'createdAt' => $this->message->created_at->toIso8601String(),
        ];
    }
}
