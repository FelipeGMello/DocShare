<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class DocumentUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(
        public string $content,
        public string $site,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('document');
    }

    public function broadcastAs(): string
    {
        return 'update';
    }
}