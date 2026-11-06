<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class CreateSiteCommand extends Command
{
    protected $signature = 'ffd:site {home_url} {--label=} {--plugin=}';
    protected $description = 'Cria um site firefly-distill e imprime site_key_id + hmac_secret';

    public function handle(): int
    {
        $site = Site::query()->create([
            'home_url' => $this->argument('home_url'),
            'site_key_id' => 'ffd_' . Str::lower(Str::random(16)),
            'hmac_secret' => Str::random(48),
            'delivery' => 'rest',
            'plugin_rest_url' => $this->option('plugin'),
            'client_label' => $this->option('label') ?: parse_url($this->argument('home_url'), PHP_URL_HOST),
            'active' => true,
        ]);
        $this->info('site_key_id=' . $site->site_key_id);
        $this->info('hmac_secret=' . $site->hmac_secret);
        return self::SUCCESS;
    }
}
