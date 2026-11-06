<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageAudit extends Model
{
    protected $fillable = [
        'site_id', 'host_id', 'path', 'url', 'phase', 'performance', 'seo',
        'accessibility', 'best_practices', 'psi_json', 'openseo_slice', 'audited_at',
    ];

    protected function casts(): array
    {
        return [
            'psi_json' => 'array',
            'openseo_slice' => 'array',
            'audited_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
