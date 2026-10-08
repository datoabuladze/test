<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engagement: plays, favorites, ratings, scores, achievements, XP, search analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per game launch. Visitors are identified only by a salted,
        // daily-rotating hash (no raw IPs are stored).
        Schema::create('game_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_hash', 64)->nullable();
            $table->string('device', 16)->nullable();   // desktop|tablet|mobile
            $table->string('locale', 8)->nullable();
            $table->string('country', 2)->nullable();   // only when supplied by a trusted edge header
            $table->string('referrer_host')->nullable();
            $table->string('load_status', 16)->default('started'); // started|loaded|failed
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['game_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('game_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('plays')->default(0);
            $table->unsignedInteger('loads')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedBigInteger('seconds_played')->default(0);
            $table->unique(['game_id', 'date']);
            $table->index('date');
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'game_id']);
            $table->index('game_id');
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->timestamps();
            $table->primary(['user_id', 'game_id']);
            $table->index('game_id');
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('score');
            $table->boolean('is_verified')->default(false);
            $table->string('verification', 24)->default('none'); // none|plausibility|replay|server
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('evidence')->nullable();       // replay/verification payload summary
            $table->timestamp('created_at')->useCurrent();
            $table->index(['game_id', 'is_verified', 'score']);
            $table->index(['user_id', 'game_id']);
        });

        // Single-use tokens issued at game start, used to tie a score to a real session.
        Schema::create('score_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seed');
            $table->timestamp('started_at');
            $table->timestamp('used_at')->nullable();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('name');
            $table->json('description');
            $table->string('icon', 64)->default('trophy');
            $table->string('period', 16)->default('lifetime'); // lifetime|daily|weekly|monthly
            $table->foreignId('game_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric', 32);              // plays|distinct_games|favorites|ratings|score|streak
            $table->unsignedBigInteger('threshold');
            $table->unsignedInteger('xp_reward')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 16)->default('lifetime'); // e.g. 2026-10-08, 2026-W41, 2026-10
            $table->timestamp('unlocked_at');
            $table->unique(['user_id', 'achievement_id', 'period_key']);
        });

        // XP ledger. The (user, reason, ref) uniqueness makes awards idempotent.
        Schema::create('xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason', 32);
            $table->string('ref', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'reason', 'ref']);
        });

        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query', 120);
            $table->string('locale', 8);
            $table->unsignedInteger('results_count');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['query', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        foreach (['search_queries', 'xp_events', 'user_achievements', 'achievements', 'score_sessions',
            'scores', 'ratings', 'favorites', 'game_stats_daily', 'game_plays'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
