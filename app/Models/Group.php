<?php

namespace App\Models;

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
}
