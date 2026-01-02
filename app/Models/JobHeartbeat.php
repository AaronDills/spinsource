<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobHeartbeat extends Model
{
    protected $table = 'job_heartbeats';
    protected $guarded = [];
    protected $casts = [
        'context' => 'array',
    ];
}
