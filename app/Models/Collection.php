<?php

namespace App\Models;

use App\Events\CollectionChanged;
use App\Events\CollectionGroupChanged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'metadata',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (self $collection) {
            event(new CollectionChanged('created', $collection));
        });

        static::updated(function (self $collection) {
            event(new CollectionChanged('updated', $collection));
        });

        static::deleted(function (self $collection) {
            // Pass a lightweight instance for deleted with last known attributes
            $collectionPayload = new self([
                'id' => $collection->id,
                'name' => $collection->name,
                'description' => $collection->description,
                'is_active' => $collection->is_active,
                'metadata' => $collection->metadata,
                'created_at' => $collection->created_at,
                'updated_at' => $collection->updated_at,
            ]);
            event(new CollectionChanged('deleted', $collectionPayload));
        });
    }

    /**
     * Les groupes associés à cette collection
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }

    /**
     * Propriétaire de la collection
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attache des groupes et émet l'événement CollectionGroupChanged
     *
     * @param  int|array<int>  $groupIds
     */
    public function attachGroups($groupIds, array $attributes = []): self
    {
        $this->groups()->attach($groupIds, $attributes);

        if (! is_array($groupIds)) {
            $groupIds = [$groupIds];
        }

        event(new CollectionGroupChanged('attached', $this->id, $groupIds, $attributes));

        return $this;
    }

    /**
     * Détache des groupes et émet l'événement CollectionGroupChanged
     *
     * @param  null|int|array<int>  $groupIds
     */
    public function detachGroups($groupIds = null): self
    {
        if ($groupIds === null) {
            $groupIds = $this->groups()->pluck('groups.id')->toArray();
        } elseif (! is_array($groupIds)) {
            $groupIds = [$groupIds];
        }

        $this->groups()->detach($groupIds);

        event(new CollectionGroupChanged('detached', $this->id, $groupIds));

        return $this;
    }

    /**
     * Synchronise les groupes et émet les événements CollectionGroupChanged
     *
     * @param  array<int>  $groupIds
     */
    public function syncGroups(array $groupIds, bool $detaching = true): self
    {
        $changes = $this->groups()->sync($groupIds, $detaching);

        if (! empty($changes['attached'])) {
            event(new CollectionGroupChanged('attached', $this->id, $changes['attached']));
        }

        if (! empty($changes['detached'])) {
            event(new CollectionGroupChanged('detached', $this->id, $changes['detached']));
        }

        if (! empty($changes['updated'])) {
            event(new CollectionGroupChanged('updated', $this->id, $changes['updated']));
        }

        return $this;
    }
}
