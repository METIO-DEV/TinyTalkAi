<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'status',
        'progress',
        'status_text',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
