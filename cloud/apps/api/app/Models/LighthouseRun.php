<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LighthouseRun extends Model
{
    protected $fillable = ['job_id', 'job_page_id', 'phase', 'scores_json'];

    protected function casts(): array
    {
        return ['scores_json' => 'array'];
    }
}
