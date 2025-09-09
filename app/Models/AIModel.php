<?php

namespace App\Models;

use App\Events\ModelGroupChanged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIModel extends Model
{
    use HasFactory;

    protected $table = 'models';

    protected $fillable = [
        'name',
        'full_name',
        'size',
        'family',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'family' => 'string',
    ];

    /**
     * Les groupes auxquels ce modèle est associé
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'model_group', 'model_id', 'group_id')
            ->withPivot([])
            ->withTimestamps()
            ->using(ModelGroup::class);
    }

    /**
     * Déclenche l'événement ModelGroupChanged lorsque des groupes sont attachés au modèle
     */
    public function attachGroups($groupIds, $attributes = [])
    {
        $this->groups()->attach($groupIds, $attributes);

        if (! is_array($groupIds)) {
            $groupIds = [$groupIds];
        }

        event(new ModelGroupChanged('attached', $this->id, $groupIds, $attributes));

        return $this;
    }

    /**
     * Déclenche l'événement ModelGroupChanged lorsque des groupes sont détachés du modèle
     */
    public function detachGroups($groupIds = null)
    {
        if ($groupIds === null) {
            $groupIds = $this->groups()->pluck('groups.id')->toArray();
        } elseif (! is_array($groupIds)) {
            $groupIds = [$groupIds];
        }

        $this->groups()->detach($groupIds);

        event(new ModelGroupChanged('detached', $this->id, $groupIds));

        return $this;
    }

    /**
     * Déclenche l'événement ModelGroupChanged lorsque les groupes du modèle sont synchronisés
     */
    public function syncGroups($groupIds, $detaching = true)
    {
        $changes = $this->groups()->sync($groupIds, $detaching);

        if (! empty($changes['attached'])) {
            event(new ModelGroupChanged('attached', $this->id, $changes['attached']));
        }

        if (! empty($changes['detached'])) {
            event(new ModelGroupChanged('detached', $this->id, $changes['detached']));
        }

        if (! empty($changes['updated'])) {
            event(new ModelGroupChanged('updated', $this->id, $changes['updated']));
        }

        return $this;
    }
}
