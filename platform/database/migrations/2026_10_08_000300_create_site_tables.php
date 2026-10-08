<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site management: homepage, CMS pages, menus, settings, themes, redirects,
 * audit log, imports, advertising and multiplayer rooms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);        // see App\Enums\HomeSectionType
            $table->json('title')->nullable();
            $table->json('config')->nullable(); // e.g. {"category":"puzzle","limit":12}
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->index(['is_enabled', 'sort_order']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120);
            $table->string('type', 16)->default('page'); // page|legal|blog|news|faq|landing
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body');
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->string('status', 16)->default('draft'); // draft|published
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'slug']);
            $table->index(['type', 'status', 'published_at']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('menu', 32);          // header|footer_main|footer_legal
            $table->json('label');
            $table->string('url', 512);          // relative path (locale prefix added) or absolute URL
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->index(['menu', 'sort_order']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('theme_versions', function (Blueprint $table) {
            $table->id();
            $table->json('tokens');              // colors, fonts, radii, spacing preset, card style
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 512)->unique();
            $table->string('to_url', 1024);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 16);        // csv|json|provider|manual
            $table->string('status', 16)->default('queued'); // queued|previewed|running|completed|failed
            $table->string('file_path')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('valid')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('external_id')->nullable();
            $table->json('raw');
            $table->json('normalized')->nullable();
            $table->string('status', 16)->default('pending'); // pending|valid|invalid|duplicate|imported|failed
            $table->json('errors')->nullable();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['import_batch_id', 'status']);
        });

        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique(); // home_top|home_mid|sidebar|game_below|category_top
            $table->string('name');
            $table->string('size_hint', 32)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16);          // direct|adsense|sponsored_game
            $table->foreignId('ad_placement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path')->nullable();
            $table->string('target_url', 1024)->nullable();
            $table->string('alt_text')->nullable();
            $table->string('adsense_slot', 32)->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('budget', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ad_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('revenue', 10, 2)->default(0); // entered manually or from reports, never simulated
            $table->unique(['ad_campaign_id', 'date']);
        });

        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('game', 32);           // tictactoe|connect4
            $table->string('status', 16)->default('waiting'); // waiting|playing|finished|expired
            $table->json('state');
            $table->unsignedInteger('version')->default(0);
            $table->string('host_token', 64);
            $table->string('guest_token', 64)->nullable();
            $table->boolean('host_ready')->default(false);
            $table->boolean('guest_ready')->default(false);
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('guest_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('host_seen_at')->nullable();
            $table->timestamp('guest_seen_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['game_rooms', 'ad_stats_daily', 'ad_campaigns', 'ad_placements', 'import_items',
            'import_batches', 'audit_logs', 'redirects', 'theme_versions', 'settings', 'menu_items',
            'pages', 'homepage_sections'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
