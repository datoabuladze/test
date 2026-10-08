<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Api;
use App\Http\Controllers\Auth;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameFrameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MultiplayerController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TagController;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$locales = implode('|', array_keys(config('platform.locales')));

Route::get('/', fn (Request $request) => redirect()->route('home', ['locale' => SetLocale::preferred($request)], 302))
    ->name('root');

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/sitemaps/{locale}/{type}.xml', [SeoController::class, 'sitemap'])
    ->where(['locale' => $locales, 'type' => 'games|categories|pages|static'])->name('sitemap.show');

Route::get('/ad/{campaign}/click', [Api\AdController::class, 'click'])->middleware('throttle:api')->name('ads.click');

// Game documents rendered inside the sandboxed player iframe.
Route::get('/frame/{game}', [GameFrameController::class, 'show'])->name('games.frame');

// ------------------------------------------------------------------ localized public site
Route::prefix('{locale}')->where(['locale' => $locales])->middleware('locale')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/games', [GameController::class, 'index'])->name('games.index');
    Route::get('/games/random', [GameController::class, 'random'])->name('games.random');
    Route::get('/game/{game:slug}', [GameController::class, 'show'])->name('games.show');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/tag/{tag:slug}', [TagController::class, 'show'])->name('tags.show');

    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/leaderboards', [LeaderboardController::class, 'index'])->name('leaderboards.index');
    Route::get('/leaderboards/{game:slug}', [LeaderboardController::class, 'show'])->name('leaderboards.show');

    Route::get('/play-together', [MultiplayerController::class, 'index'])->name('rooms.index');
    Route::get('/play-together/{room:code}', [MultiplayerController::class, 'show'])->name('rooms.show');

    Route::get('/u/{nickname}', [ProfileController::class, 'show'])->name('profiles.show');

    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/p/{slug}', [PageController::class, 'show'])->name('pages.show');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [Auth\LoginController::class, 'create'])->name('login');
        Route::post('/login', [Auth\LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
        Route::get('/register', [Auth\RegisterController::class, 'create'])->name('register');
        Route::post('/register', [Auth\RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
        Route::get('/forgot-password', [Auth\PasswordResetController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [Auth\PasswordResetController::class, 'email'])->middleware('throttle:password-email')->name('password.email');
        Route::get('/reset-password/{token}', [Auth\PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('/reset-password', [Auth\PasswordResetController::class, 'update'])->middleware('throttle:password-email')->name('password.update');
    });

    Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
        Route::get('/', [Account\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/favorites', [Account\DashboardController::class, 'favorites'])->name('favorites');
        Route::get('/history', [Account\DashboardController::class, 'history'])->name('history');
        Route::get('/achievements', [Account\DashboardController::class, 'achievements'])->name('achievements');
        Route::get('/settings', [Account\SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings', [Account\SettingsController::class, 'update'])->name('settings.update');
        Route::put('/password', [Account\SettingsController::class, 'password'])->name('password');
        Route::post('/avatar', [Account\SettingsController::class, 'avatar'])->name('avatar');
        Route::delete('/', [Account\SettingsController::class, 'destroy'])->name('destroy');
        Route::get('/verify-email', [Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    });
});

// ------------------------------------------------------------------ non-localized account actions
Route::middleware(['locale.session'])->group(function () {
    Route::post('/logout', [Auth\LoginController::class, 'destroy'])->middleware('auth')->name('logout');
    Route::get('/email/verify/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])
        ->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Auth\EmailVerificationController::class, 'send'])
        ->middleware(['auth', 'throttle:verification'])->name('verification.send');
});

// ------------------------------------------------------------------ JSON endpoints used by the player and UI
Route::prefix('api')->name('api.')->middleware(['locale.session', 'throttle:api'])->group(function () {
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
    Route::post('/games/{game:slug}/plays', [Api\PlayController::class, 'start'])->name('plays.start');
    Route::post('/plays/{play}/status', [Api\PlayController::class, 'status'])->name('plays.status');
    Route::post('/games/{game:slug}/report', [Api\ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
    Route::post('/games/by-ids', [Api\GameListController::class, 'byIds'])->name('games.by-ids');

    Route::middleware('auth')->group(function () {
        Route::post('/games/{game:slug}/favorite', [Api\FavoriteController::class, 'toggle'])->name('favorites.toggle');
        Route::post('/games/{game:slug}/rating', [Api\RatingController::class, 'store'])->name('ratings.store');
        Route::post('/games/{game:slug}/score-session', [Api\ScoreController::class, 'session'])->name('scores.session');
        Route::post('/games/{game:slug}/scores', [Api\ScoreController::class, 'store'])->middleware('throttle:scores')->name('scores.store');
    });

    Route::post('/rooms', [Api\RoomController::class, 'store'])->middleware('throttle:rooms')->name('rooms.store');
    Route::post('/rooms/{room:code}/join', [Api\RoomController::class, 'join'])->name('rooms.join');
    Route::get('/rooms/{room:code}', [Api\RoomController::class, 'state'])->name('rooms.state');
    Route::post('/rooms/{room:code}/ready', [Api\RoomController::class, 'ready'])->name('rooms.ready');
    Route::post('/rooms/{room:code}/move', [Api\RoomController::class, 'move'])->name('rooms.move');
    Route::post('/rooms/{room:code}/rematch', [Api\RoomController::class, 'rematch'])->name('rooms.rematch');
});

// ------------------------------------------------------------------ admin
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission:admin.access'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('permission:games.manage')->group(function () {
        Route::get('games', [Admin\GameController::class, 'index'])->name('games.index');
        Route::get('games/create', [Admin\GameController::class, 'create'])->name('games.create');
        Route::post('games', [Admin\GameController::class, 'store'])->name('games.store');
        Route::get('games/{game}/edit', [Admin\GameController::class, 'edit'])->name('games.edit')->withTrashed();
        Route::put('games/{game}', [Admin\GameController::class, 'update'])->name('games.update')->withTrashed();
        Route::delete('games/{game}', [Admin\GameController::class, 'destroy'])->name('games.destroy')->withTrashed();
        Route::post('games/{game}/restore', [Admin\GameController::class, 'restore'])->name('games.restore')->withTrashed();
        Route::post('games/{game}/package', [Admin\GameController::class, 'uploadPackage'])->name('games.package');
        Route::post('games/{game}/check', [Admin\GameController::class, 'check'])->name('games.check');
        Route::get('games/{game}/preview', [Admin\GameController::class, 'preview'])->name('games.preview');
        Route::post('games/bulk', [Admin\GameController::class, 'bulk'])->name('games.bulk');
        Route::post('games/{game}/publish', [Admin\GameController::class, 'publish'])->middleware('permission:games.publish')->name('games.publish');
        Route::post('games/{game}/unpublish', [Admin\GameController::class, 'unpublish'])->middleware('permission:games.publish')->name('games.unpublish');
        Route::post('games/{game}/rights', [Admin\GameController::class, 'rights'])->middleware('permission:games.rights')->name('games.rights');
    });

    Route::middleware('permission:categories.manage')->group(function () {
        Route::resource('categories', Admin\CategoryController::class)->except('show');
        Route::post('categories/reorder', [Admin\CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::resource('tags', Admin\TagController::class)->except(['show', 'create', 'edit']);
    });

    Route::middleware('permission:imports.manage')->group(function () {
        Route::resource('providers', Admin\ProviderController::class)->except('show');
        Route::get('imports', [Admin\ImportController::class, 'index'])->name('imports.index');
        Route::post('imports', [Admin\ImportController::class, 'store'])->name('imports.store');
        Route::get('imports/{batch}', [Admin\ImportController::class, 'show'])->name('imports.show');
        Route::post('imports/{batch}/run', [Admin\ImportController::class, 'run'])->name('imports.run');
    });

    Route::middleware('permission:reports.manage')->group(function () {
        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/{report}', [Admin\ReportController::class, 'update'])->name('reports.update');
    });

    Route::middleware('permission:content.manage')->group(function () {
        Route::resource('pages', Admin\PageController::class)->except('show');
        Route::get('menus', [Admin\MenuController::class, 'index'])->name('menus.index');
        Route::post('menus', [Admin\MenuController::class, 'store'])->name('menus.store');
        Route::put('menus/{item}', [Admin\MenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{item}', [Admin\MenuController::class, 'destroy'])->name('menus.destroy');
        Route::get('homepage', [Admin\HomepageController::class, 'index'])->name('homepage.index');
        Route::post('homepage', [Admin\HomepageController::class, 'store'])->name('homepage.store');
        Route::put('homepage/{section}', [Admin\HomepageController::class, 'update'])->name('homepage.update');
        Route::delete('homepage/{section}', [Admin\HomepageController::class, 'destroy'])->name('homepage.destroy');
        Route::post('homepage-order', [Admin\HomepageController::class, 'reorder'])->name('homepage.reorder');
    });

    Route::middleware('permission:design.manage')->group(function () {
        Route::get('design', [Admin\DesignController::class, 'index'])->name('design.index');
        Route::post('design', [Admin\DesignController::class, 'store'])->name('design.store');
        Route::post('design/{version}/activate', [Admin\DesignController::class, 'activate'])->name('design.activate');
        Route::post('design/branding', [Admin\DesignController::class, 'branding'])->name('design.branding');
    });

    Route::middleware('permission:users.moderate')->group(function () {
        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::post('users/{user}/suspend', [Admin\UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/unsuspend', [Admin\UserController::class, 'unsuspend'])->name('users.unsuspend');
        Route::post('users/{user}/role', [Admin\UserController::class, 'role'])->middleware('permission:users.manage')->name('users.role');
    });

    Route::middleware('permission:ads.manage')->group(function () {
        Route::get('ads', [Admin\AdController::class, 'index'])->name('ads.index');
        Route::post('ads/placements/{placement}', [Admin\AdController::class, 'placement'])->name('ads.placement');
        Route::post('ads/campaigns', [Admin\AdController::class, 'store'])->name('ads.store');
        Route::put('ads/campaigns/{campaign}', [Admin\AdController::class, 'update'])->name('ads.update');
        Route::delete('ads/campaigns/{campaign}', [Admin\AdController::class, 'destroy'])->name('ads.destroy');
    });

    Route::middleware('permission:seo.manage')->group(function () {
        Route::get('seo', [Admin\SeoController::class, 'index'])->name('seo.index');
        Route::post('redirects', [Admin\SeoController::class, 'storeRedirect'])->name('redirects.store');
        Route::delete('redirects/{redirect}', [Admin\SeoController::class, 'destroyRedirect'])->name('redirects.destroy');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::get('translations', [Admin\TranslationController::class, 'index'])->name('translations.index');
        Route::post('translations', [Admin\TranslationController::class, 'update'])->name('translations.update');
    });

    Route::get('analytics', [Admin\AnalyticsController::class, 'index'])->middleware('permission:analytics.view')->name('analytics.index');
    Route::get('audit', [Admin\AuditController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');
});
