<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CollectionGroupChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action; // attached | detached | updated

    public int $collection_id;

    /** @var int[] */
    public array $group_ids;

    public array $pivotAttributes;

    public function __construct(string $action, int $collectionId, array $groupIds, array $pivotAttributes = [])
    {
        $this->action = $action;
        $this->collection_id = $collectionId;
        $this->group_ids = $groupIds;
        $this->pivotAttributes = $pivotAttributes;
    }

    public function broadcastOn(): array
    {
        return [new Channel('collections')];
    }

    public function broadcastAs(): string
    {
        return 'CollectionGroupChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'collection_id' => $this->collection_id,
            'group_ids' => $this->group_ids,
            'attributes' => $this->pivotAttributes,
        ];
    }
}
