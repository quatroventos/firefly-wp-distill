<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suggestion extends Model
{
    protected $fillable = [
        'site_id', 'host_id', 'url', 'category', 'severity', 'title', 'body',
        'status', 'source', 'dedupe_hash', 'payload_json',
    ];

    protected function casts(): array
    {
        return ['payload_json' => 'array'];
    }
}
