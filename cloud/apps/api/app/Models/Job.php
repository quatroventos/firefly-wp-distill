<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    protected $table = 'jobs_ffd';

    protected $fillable = [
        'site_id', 'host_id', 'scope', 'status', 'urls_json', 'job_token', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'urls_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(JobPage::class);
    }
}
