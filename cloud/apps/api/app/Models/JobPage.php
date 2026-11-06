<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPage extends Model
{
    protected $fillable = [
        'job_id', 'host_id', 'path', 'status', 'origin_hash', 'output_hash', 'output_relpath',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
