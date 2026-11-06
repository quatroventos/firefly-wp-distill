<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('home_url');
            $table->string('site_key_id')->unique();
            $table->text('hmac_secret');
            $table->string('delivery')->default('rest');
            $table->text('sftp_meta')->nullable();
            $table->string('plugin_rest_url')->nullable();
            $table->string('openseo_project_id')->nullable();
            $table->string('client_label')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('host');
            $table->unsignedBigInteger('post_id')->default(0);
            $table->json('routes_json')->nullable();
            $table->boolean('https')->default(true);
            $table->boolean('flag_robots_index')->default(true);
            $table->boolean('flag_sitemap')->default(true);
            $table->boolean('flag_llm_txt')->default(true);
            $table->boolean('static_enabled')->default(false);
            $table->string('openseo_project_id')->nullable();
            $table->string('gsc_property')->nullable();
            $table->string('current_build_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['site_id', 'host']);
        });

        Schema::create('jobs_ffd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('host_id')->nullable()->constrained('hosts')->nullOnDelete();
            $table->string('scope');
            $table->string('status')->default('queued');
            $table->json('urls_json')->nullable();
            $table->string('job_token')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('job_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs_ffd')->cascadeOnDelete();
            $table->foreignId('host_id')->nullable()->constrained('hosts')->nullOnDelete();
            $table->string('path');
            $table->string('status')->default('pending');
            $table->char('origin_hash', 64)->nullable();
            $table->char('output_hash', 64)->nullable();
            $table->string('output_relpath')->nullable();
            $table->timestamps();
        });

        Schema::create('builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs_ffd')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('hosts')->cascadeOnDelete();
            $table->string('build_id');
            $table->string('checksum')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lighthouse_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs_ffd')->cascadeOnDelete();
            $table->foreignId('job_page_id')->nullable()->constrained('job_pages')->nullOnDelete();
            $table->string('phase');
            $table->json('scores_json')->nullable();
            $table->timestamps();
        });

        Schema::create('page_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('host_id')->nullable()->constrained('hosts')->nullOnDelete();
            $table->string('path');
            $table->text('url');
            $table->string('phase');
            $table->unsignedSmallInteger('performance')->nullable();
            $table->unsignedSmallInteger('seo')->nullable();
            $table->unsignedSmallInteger('accessibility')->nullable();
            $table->unsignedSmallInteger('best_practices')->nullable();
            $table->json('psi_json')->nullable();
            $table->json('openseo_slice')->nullable();
            $table->timestamp('audited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('host_id')->nullable()->constrained('hosts')->nullOnDelete();
            $table->text('url');
            $table->string('category');
            $table->string('severity');
            $table->string('title');
            $table->text('body');
            $table->string('status')->default('open');
            $table->string('source');
            $table->char('dedupe_hash', 64);
            $table->json('payload_json')->nullable();
            $table->timestamps();
            $table->unique('dedupe_hash');
        });

        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs_ffd')->cascadeOnDelete();
            $table->string('kind');
            $table->string('s3_key');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
        Schema::dropIfExists('suggestions');
        Schema::dropIfExists('page_audits');
        Schema::dropIfExists('lighthouse_runs');
        Schema::dropIfExists('builds');
        Schema::dropIfExists('job_pages');
        Schema::dropIfExists('jobs_ffd');
        Schema::dropIfExists('hosts');
        Schema::dropIfExists('sites');
    }
};
