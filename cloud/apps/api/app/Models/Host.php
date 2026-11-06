<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Host extends Model
{
    protected $fillable = [
        'site_id', 'host', 'post_id', 'routes_json', 'https',
        'flag_robots_index', 'flag_sitemap', 'flag_llm_txt', 'static_enabled',
        'openseo_project_id', 'gsc_property', 'current_build_id', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'routes_json' => 'array',
            'https' => 'boolean',
            'flag_robots_index' => 'boolean',
            'flag_sitemap' => 'boolean',
            'flag_llm_txt' => 'boolean',
            'static_enabled' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }
}
