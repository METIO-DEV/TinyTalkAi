<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserGroupChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action; // attached | detached | updated | synced

    public int $user_id;

    /** @var array<int> */
    public array $group_ids;

    public array $attributes;

    /**
     * @param  array<int>  $groupIds
     */
    public function __construct(string $action, int $userId, array $groupIds = [], array $attributes = [])
    {
        $this->action = $action;
        $this->user_id = $userId;
        $this->group_ids = $groupIds;
        $this->attributes = $attributes;
    }

    public function broadcastOn(): array
    {
        return [new Channel('users')];
    }

    public function broadcastAs(): string
    {
        return 'UserGroupChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'user_id' => $this->user_id,
            'group_ids' => $this->group_ids,
            'attributes' => $this->attributes,
        ];
    }
}
