<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\ThemeVersion;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Paginator::defaultView('components.pagination');

        Password::defaults(fn () => Password::min(10)->letters()->numbers()
            ->when($this->app->isProduction(), fn (Password $p) => $p->uncompromised()));

        $this->configureRateLimiting();
        $this->shareLayoutData();
    }

    private function configureRateLimiting(): void
    {
        $key = fn (Request $r) => $r->user()?->id ?: $r->ip();

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($key($r)));
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
        RateLimiter::for('password-email', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('verification', fn (Request $r) => Limit::perMinute(3)->by($key($r)));
        RateLimiter::for('reports', fn (Request $r) => Limit::perHour(10)->by($key($r)));
        RateLimiter::for('scores', fn (Request $r) => Limit::perMinute(10)->by($key($r)));
        RateLimiter::for('rooms', fn (Request $r) => Limit::perHour(30)->by($key($r)));
    }

    private function shareLayoutData(): void
    {
        View::composer(['layouts.app', 'partials.*'], function ($view) {
            if (! Schema::hasTable('categories')) {
                return;
            }
            $view->with('navCategories', Cache::remember('categories.tree', 600, fn () => Category::query()
                ->where('is_active', true)->where('show_in_menu', true)->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')->get()));
            $view->with('menus', Cache::remember('menus.all', 600, fn () => MenuItem::query()
                ->where('is_enabled', true)->orderBy('sort_order')->get()->groupBy('menu')));
            $view->with('theme', ThemeVersion::activeTokens());
            $view->with('branding', Setting::get('branding', []));
        });
    }
}
