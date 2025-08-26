<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ModelGroup extends Pivot
{
    protected $table = 'model_group';

    protected $casts = [
        'model_id' => 'integer',
        'group_id' => 'integer',
    ];
}
