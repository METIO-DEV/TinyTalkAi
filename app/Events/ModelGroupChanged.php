<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelGroupChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action; // attached | detached | updated

    public int $model_id;

    /** @var int[] */
    public array $group_ids;

    public array $pivotAttributes;

    public function __construct(string $action, int $modelId, array $groupIds, array $pivotAttributes = [])
    {
        $this->action = $action;
        $this->model_id = $modelId;
        $this->group_ids = $groupIds;
        $this->pivotAttributes = $pivotAttributes;
    }

    public function broadcastOn(): array
    {
        return [new Channel('models')];
    }

    public function broadcastAs(): string
    {
        return 'ModelGroupChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'model_id' => $this->model_id,
            'group_ids' => $this->group_ids,
            'attributes' => $this->pivotAttributes,
        ];
    }
}
