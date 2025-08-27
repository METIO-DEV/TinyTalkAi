<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    protected $fillable = ['name', 'guard_name'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'model_has_roles');
    }
}
