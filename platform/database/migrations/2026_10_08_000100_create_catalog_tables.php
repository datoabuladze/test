<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog: providers, categories, tags, games and their relations.
 *
 * Translatable text is stored as JSON objects keyed by locale
 * (e.g. {"en": "Snake", "ka": "გველი"}); see App\Models\Concerns\HasTranslations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('adapter', 64);              // key in config('platform.import.adapters')
            $table->string('website_url')->nullable();
            $table->json('allowed_embed_hosts')->nullable(); // hosts an iframe embed_url may point at
            $table->json('settings')->nullable();       // non-secret adapter settings; secrets live in .env
            $table->text('agreement_notes')->nullable(); // summary of distribution agreement / terms
            $table->string('agreement_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('health_status', 16)->default('unknown'); // unknown|healthy|degraded|down
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug', 80)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('color', 16)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->boolean('show_on_home')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->json('name');
            $table->timestamps();
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->json('title');
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->json('instructions')->nullable();
            $table->json('controls')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();

            // How the game is played. See App\Enums\GameEngine.
            $table->string('engine', 24)->index();
            $table->string('entry_path')->nullable();   // path on the games disk / public games dir
            $table->string('embed_url', 2048)->nullable(); // authorized third-party iframe URL
            $table->json('engine_config')->nullable();  // e.g. Unity loader/data/framework/wasm file names
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('orientation', 16)->default('any'); // any|landscape|portrait

            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_color', 16)->nullable();

            // Provenance & rights
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('developer')->nullable();
            $table->string('developer_url')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('license_type', 64)->nullable();     // e.g. MIT, CC-BY-4.0, Provider agreement, Original
            $table->string('license_url', 2048)->nullable();
            $table->text('license_notes')->nullable();
            $table->text('attribution_text')->nullable();
            $table->string('hosting_method', 24)->nullable();   // self_hosted|iframe_embed
            $table->boolean('commercial_use_allowed')->default(false);
            $table->boolean('ads_allowed')->default(false);
            $table->boolean('modifications_allowed')->default(false);
            $table->boolean('thumbnail_rights')->default(false);
            $table->boolean('embed_authorized')->default(false);
            $table->string('rights_status', 16)->default('unverified')->index(); // unverified|verified|rejected
            $table->timestamp('rights_verified_at')->nullable();
            $table->foreignId('rights_verified_by')->nullable()->constrained('users')->nullOnDelete();

            // Lifecycle
            $table->string('status', 16)->default('draft')->index(); // draft|pending|published|unpublished|archived
            $table->timestamp('published_at')->nullable(); // future value = scheduled

            // Technical validation
            $table->string('launch_status', 16)->default('untested'); // untested|ok|failed
            $table->string('flash_compatibility', 16)->nullable();   // untested|compatible|partial|unsupported|broken
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_check_message')->nullable();

            // Discovery metadata
            $table->json('input_types')->nullable();    // keyboard|mouse|touch|gamepad
            $table->json('devices')->nullable();        // desktop|tablet|mobile
            $table->json('languages')->nullable();
            $table->unsignedTinyInteger('min_age')->default(0);
            $table->string('difficulty', 16)->nullable(); // easy|medium|hard
            $table->string('session_length', 16)->nullable(); // short|medium|long
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_editors_pick')->default(false);
            $table->boolean('is_multiplayer')->default(false);
            $table->boolean('is_mobile_friendly')->default(false);
            $table->boolean('is_original')->default(false);
            $table->string('score_mode', 16)->default('none'); // none|casual|verified

            // Counters (denormalized; recomputed by scheduled jobs)
            $table->unsignedBigInteger('play_count')->default(0);
            $table->unsignedInteger('favorites_count')->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->double('popularity_score')->default(0);
            $table->double('trending_score')->default(0);

            $table->text('search_text')->nullable();    // lowercase multi-locale text for search
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider_id', 'external_id']);
            $table->index(['status', 'rights_status', 'published_at']);
            $table->index('popularity_score');
            $table->index('trending_score');
            $table->index('play_count');
        });

        Schema::create('category_game', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['game_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('game_tag', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['game_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('game_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 32); // not_loading|crashes|controls|inappropriate|copyright|other
            $table->text('message')->nullable();
            $table->string('status', 16)->default('open')->index(); // open|resolved|dismissed
            $table->string('ip_hash', 64)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_reports');
        Schema::dropIfExists('game_tag');
        Schema::dropIfExists('category_game');
        Schema::dropIfExists('games');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('providers');
    }
};
