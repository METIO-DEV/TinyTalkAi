<?php

namespace App\Models;

use App\Events\CollectionGroupChanged;
use App\Events\ModelGroupChanged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Les collections associées à ce groupe
     */
    public function collections()
    {
        return $this->belongsToMany(Collection::class);
    }

    /**
     * Les modèles AI associés à ce groupe
     */
    public function models()
    {
        return $this->belongsToMany(AIModel::class, 'model_group', 'group_id', 'model_id');
    }

    /**
     * Synchronise les collections et émet des événements de mise à jour par collection.
     *
     * @param  array<int>  $collectionIds
     */
    public function syncCollections(array $collectionIds, bool $detaching = true): self
    {
        $changes = $this->collections()->sync($collectionIds, $detaching);

        if (! empty($changes['attached'])) {
            foreach ($changes['attached'] as $collectionId) {
                event(new CollectionGroupChanged('attached', $collectionId, [$this->id]));
            }
        }

        if (! empty($changes['detached'])) {
            foreach ($changes['detached'] as $collectionId) {
                event(new CollectionGroupChanged('detached', $collectionId, [$this->id]));
            }
        }

        if (! empty($changes['updated'])) {
            foreach ($changes['updated'] as $collectionId) {
                event(new CollectionGroupChanged('updated', $collectionId, [$this->id]));
            }
        }

        return $this;
    }

    /**
     * Synchronise les modèles et émet des événements de mise à jour par modèle.
     *
     * @param  array<int>  $modelIds
     */
    public function syncModels(array $modelIds, bool $detaching = true): self
    {
        $changes = $this->models()->sync($modelIds, $detaching);

        if (! empty($changes['attached'])) {
            foreach ($changes['attached'] as $modelId) {
                event(new ModelGroupChanged('attached', $modelId, [$this->id]));
            }
        }

        if (! empty($changes['detached'])) {
            foreach ($changes['detached'] as $modelId) {
                event(new ModelGroupChanged('detached', $modelId, [$this->id]));
            }
        }

        if (! empty($changes['updated'])) {
            foreach ($changes['updated'] as $modelId) {
                event(new ModelGroupChanged('updated', $modelId, [$this->id]));
            }
        }

        return $this;
    }
}
