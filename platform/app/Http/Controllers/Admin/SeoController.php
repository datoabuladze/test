<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Game;
use App\Models\Page;
use App\Models\Redirect;
use App\Services\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function index(Request $request): View
    {
        $locales = array_keys(config('platform.locales'));
        $missing = fn (string $model, string $column, ?\Closure $scope = null) => collect($locales)->mapWithKeys(fn ($code) => [
            $code => $model::query()->when($scope, $scope)
                ->where(fn (Builder $q) => $q->whereNull($column)->orWhereNull("$column->$code")->orWhere("$column->$code", ''))
                ->count(),
        ])->all();

        $public = fn (Builder $q) => $q->public();

        $overview = [
            'games_public' => Game::query()->public()->count(),
            'games_missing_seo' => $missing(Game::class, 'seo_description', $public),
            'games_missing_description' => $missing(Game::class, 'description', $public),
            'categories_active' => Category::query()->where('is_active', true)->count(),
            'categories_missing_description' => $missing(Category::class, 'description', fn (Builder $q) => $q->where('is_active', true)),
            'pages_published' => Page::query()->published()->count(),
            'pages_missing_seo' => $missing(Page::class, 'seo_description', fn (Builder $q) => $q->published()),
        ];

        $q = trim((string) $request->query('q', ''));
        $redirects = Redirect::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('from_path', 'like', "%$q%")->orWhere('to_url', 'like', "%$q%")))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.seo.index', [
            'overview' => $overview,
            'locales' => config('platform.locales'),
            'redirects' => $redirects,
            'q' => $q,
            'ga4' => config('platform.analytics.ga4_measurement_id'),
            'verification' => config('platform.analytics.search_console_verification'),
        ]);
    }

    public function storeRedirect(Request $request): RedirectResponse
    {
        if ($request->filled('from_path')) {
            // Normalize to the form the redirect middleware matches: "/a/b" (no trailing slash).
            $from = '/'.trim((string) $request->input('from_path'), " /\t");
            $request->merge(['from_path' => $from]);
        }

        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:512', 'starts_with:/', 'not_regex:#^//#', 'regex:#^/[^\s?\#]*$#',
                Rule::unique('redirects', 'from_path')],
            'to_url' => ['required', 'string', 'max:1024', function ($attr, $value, $fail) {
                $v = (string) $value;
                if (str_starts_with($v, '/') && ! str_starts_with($v, '//')) {
                    return;
                }
                if (! str_starts_with($v, 'https://') || ! filter_var($v, FILTER_VALIDATE_URL)) {
                    $fail('The target must be a relative path starting with "/" or an https:// URL.');
                }
            }],
            'status_code' => ['required', 'integer', Rule::in([301, 302])],
        ], [
            'from_path.regex' => 'The source path cannot contain spaces, a query string or a fragment.',
            'from_path.not_regex' => 'The source path must be a path on this site.',
        ]);

        if (rtrim($data['to_url'], '/') === rtrim($data['from_path'], '/')) {
            return back()->withInput()->withErrors(['to_url' => 'A redirect cannot point to itself.']);
        }

        $redirect = Redirect::query()->create($data);
        Cache::forget('redirects.map');
        Audit::log('redirect.create', $redirect, $data);

        return back()->with('status', "Redirect {$redirect->from_path} → {$redirect->to_url} added.");
    }

    public function destroyRedirect(Redirect $redirect): RedirectResponse
    {
        Audit::log('redirect.delete', $redirect, ['from_path' => $redirect->from_path, 'to_url' => $redirect->to_url]);
        $redirect->delete();
        Cache::forget('redirects.map');

        return back()->with('status', 'Redirect removed.');
    }
}
