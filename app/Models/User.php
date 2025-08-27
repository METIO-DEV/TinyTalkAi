<?php

namespace App\Models;

use App\Events\UserGroupChanged;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }

    /**
     * Collections possédées par l'utilisateur
     */
    public function collections()
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * Attach groups and emit UserGroupChanged.
     *
     * @param  int|array<int>  $groupIds
     */
    public function attachGroups($groupIds, array $attributes = []): self
    {
        $this->groups()->attach($groupIds, $attributes);

        if (! is_array($groupIds)) {
            $groupIds = [$groupIds];
        }

        event(new UserGroupChanged('attached', $this->id, $groupIds, $attributes));

        return $this;
    }

    /**
     * Detach groups and emit UserGroupChanged.
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

        event(new UserGroupChanged('detached', $this->id, $groupIds));

        return $this;
    }

    /**
     * Sync groups and emit UserGroupChanged for attached/detached/updated.
     *
     * @param  array<int>  $groupIds
     */
    public function syncGroups(array $groupIds, bool $detaching = true): self
    {
        $changes = $this->groups()->sync($groupIds, $detaching);

        if (! empty($changes['attached'])) {
            event(new UserGroupChanged('attached', $this->id, $changes['attached']));
        }

        if (! empty($changes['detached'])) {
            event(new UserGroupChanged('detached', $this->id, $changes['detached']));
        }

        if (! empty($changes['updated'])) {
            event(new UserGroupChanged('updated', $this->id, $changes['updated']));
        }

        // Optionally emit a generic synced event
        event(new UserGroupChanged('synced', $this->id, $groupIds));

        return $this;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }
}
