<?php

namespace App\Events;

use App\Models\Collection;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CollectionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action; // created | updated | deleted

    public array $payload;

    public function __construct(string $action, Collection $collection)
    {
        $this->action = $action;
        $this->payload = [
            'id' => $collection->id,
            'name' => $collection->name,
            'description' => $collection->description,
            'is_active' => $collection->is_active,
            'metadata' => $collection->metadata,
            'created_at' => optional($collection->created_at)?->toISOString(),
            'updated_at' => optional($collection->updated_at)?->toISOString(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('collections')];
    }

    public function broadcastAs(): string
    {
        return 'CollectionChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'collection' => $this->payload,
        ];
    }
}
