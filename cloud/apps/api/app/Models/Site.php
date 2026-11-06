<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = [
        'home_url', 'site_key_id', 'hmac_secret', 'delivery', 'sftp_meta',
        'plugin_rest_url', 'openseo_project_id', 'client_label', 'active',
    ];

    protected $hidden = ['hmac_secret', 'sftp_meta'];

    protected function casts(): array
    {
        return [
            'hmac_secret' => 'encrypted',
            'sftp_meta' => 'encrypted:array',
            'active' => 'boolean',
        ];
    }

    public function hosts(): HasMany
    {
        return $this->hasMany(Host::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }
}
