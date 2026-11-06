<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Build extends Model
{
    protected $fillable = [
        'job_id', 'host_id', 'build_id', 'checksum', 'delivered_at', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
