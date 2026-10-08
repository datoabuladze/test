<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlashCompatibility;
use App\Enums\GameEngine;
use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GameController as PublicGameController;
use App\Models\Category;
use App\Models\Game;
use App\Models\Provider;
use App\Models\Tag;
use App\Services\Audit;
use App\Services\GameCatalog;
use App\Services\GamePackageInstaller;
use App\Services\GamePublisher;
use App\Services\ImageProcessor;
use App\Services\LaunchChecker;
use App\Services\PublishException;
use App\Support\Translatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameController extends Controller
{
    /** Changing any of these after verification sends the game back to rights review. */
    private const RIGHTS_FIELDS = [
        'license_type', 'license_url', 'source_url', 'hosting_method', 'embed_url', 'provider_id',
        'commercial_use_allowed', 'ads_allowed', 'modifications_allowed', 'thumbnail_rights', 'embed_authorized',
    ];

    public function index(Request $request): View
    {
        $q = Game::query()->with(['provider:id,name', 'categories:id,slug,name'])
            ->select(['id', 'slug', 'title', 'engine', 'status', 'rights_status', 'launch_status', 'flash_compatibility',
                'provider_id', 'thumbnail_path', 'thumbnail_color', 'play_count', 'is_featured', 'published_at', 'updated_at', 'deleted_at']);

        if ($request->boolean('trashed')) {
            $q->onlyTrashed();
        }
        if ($s = trim((string) $request->query('q'))) {
            $needle = '%'.mb_strtolower($s).'%';
            $q->where(fn ($w) => $w->where('search_text', 'like', $needle)->orWhere('slug', 'like', $needle)->orWhere('external_id', $s));
        }
        foreach (['status', 'rights_status', 'launch_status', 'engine'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($p = $request->query('provider')) {
            $q->where('provider_id', $p);
        }
        if ($c = $request->query('category')) {
            $q->whereHas('categories', fn ($w) => $w->where('categories.slug', $c));
        }
        $sort = $request->query('sort', 'updated');
        match ($sort) {
            'plays' => $q->orderByDesc('play_count'),
            'title' => $q->orderBy('slug'),
            'published' => $q->orderByDesc('published_at'),
            default => $q->orderByDesc('updated_at'),
        };

        return view('admin.games.index', [
            'games' => $q->paginate(30)->withQueryString(),
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('sort_order')->get(['id', 'slug', 'name']),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Game([
            'engine' => GameEngine::Html5, 'status' => GameStatus::Draft, 'orientation' => 'any',
            'rights_status' => RightsStatus::Unverified, 'hosting_method' => 'self_hosted',
        ]));
    }

    public function edit(Game $game): View
    {
        $game->load(['categories:id', 'tags:id,slug', 'provider']);

        return $this->form($game);
    }

    private function form(Game $game): View
    {
        return view('admin.games.form', [
            'game' => $game,
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name', 'allowed_embed_hosts']),
            'categories' => Category::query()->orderBy('sort_order')->get(['id', 'slug', 'name', 'parent_id']),
            'blockers' => $game->exists ? app(GamePublisher::class)->blockers($game) : [],
            'frameUrl' => $game->exists ? PublicGameController::frameUrl($game) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $game = new Game(['status' => GameStatus::Draft, 'rights_status' => RightsStatus::Unverified]);
        $this->fill($game, $request);
        $game->save();
        $this->syncRelations($game, $request);
        Audit::log('game.create', $game);

        return redirect()->route('admin.games.edit', $game)->with('status', 'Game created as a draft. Upload files, verify rights, then publish.');
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $before = $game->only(self::RIGHTS_FIELDS);
        $this->fill($game, $request);

        $rightsChanged = $game->rights_status === RightsStatus::Verified
            && collect(self::RIGHTS_FIELDS)->contains(fn ($f) => $game->isDirty($f));
        if ($rightsChanged) {
            $game->rights_status = RightsStatus::Unverified;
            $game->rights_verified_at = null;
            $game->rights_verified_by = null;
        }
        $game->save();
        $this->syncRelations($game, $request);
        GameCatalog::flush();
        Audit::log('game.update', $game, $rightsChanged ? ['rights_reset' => true, 'before' => $before] : []);

        return back()->with('status', $rightsChanged
            ? 'Saved. Licensing details changed, so rights must be verified again before the game is public.'
            : 'Game saved.');
    }

    private function fill(Game $game, Request $request): void
    {
        $data = $request->validate([
            ...Translatable::rules('title', true, 120),
            ...Translatable::rules('short_description', false, 300),
            ...Translatable::rules('description', false, 10000),
            ...Translatable::rules('instructions', false, 5000),
            ...Translatable::rules('controls', false, 2000),
            ...Translatable::rules('seo_title', false, 70),
            ...Translatable::rules('seo_description', false, 170),
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('games', 'slug')->ignore($game->id)],
            'engine' => ['required', Rule::enum(GameEngine::class)],
            'entry_path' => ['nullable', 'string', 'max:255', 'regex:#^(games|game-files)/[A-Za-z0-9._/-]+$#', 'not_regex:#\.\.#'],
            'embed_url' => ['nullable', 'url:https', 'max:2048'],
            'width' => ['nullable', 'integer', 'between:200,4096'],
            'height' => ['nullable', 'integer', 'between:200,4096'],
            'orientation' => ['required', Rule::in(['any', 'landscape', 'portrait'])],
            'thumbnail_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.config('platform.uploads.max_thumbnail_kb')],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'external_id' => ['nullable', 'string', 'max:191'],
            'developer' => ['nullable', 'string', 'max:191'],
            'developer_url' => ['nullable', 'url', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'license_type' => ['nullable', 'string', 'max:64'],
            'license_url' => ['nullable', 'url', 'max:2048'],
            'license_notes' => ['nullable', 'string', 'max:5000'],
            'attribution_text' => ['nullable', 'string', 'max:2000'],
            'hosting_method' => ['nullable', Rule::in(['self_hosted', 'iframe_embed'])],
            'flash_compatibility' => ['nullable', Rule::enum(FlashCompatibility::class)],
            'input_types' => ['nullable', 'array'], 'input_types.*' => [Rule::in(['keyboard', 'mouse', 'touch', 'gamepad'])],
            'devices' => ['nullable', 'array'], 'devices.*' => [Rule::in(['desktop', 'tablet', 'mobile'])],
            'languages' => ['nullable', 'array'], 'languages.*' => [Rule::in(array_keys(config('platform.locales')))],
            'min_age' => ['nullable', 'integer', 'between:0,18'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'session_length' => ['nullable', Rule::in(['short', 'medium', 'long'])],
            'score_mode' => ['required', Rule::in(['none', 'casual', 'verified'])],
            'categories' => ['nullable', 'array'], 'categories.*' => ['integer', 'exists:categories,id'],
            'primary_category' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['title', 'short_description', 'description', 'instructions', 'controls', 'seo_title', 'seo_description'] as $f) {
            $game->setAttribute($f, Translatable::clean($data[$f] ?? null));
        }
        $game->fill(collect($data)->only([
            'engine', 'entry_path', 'embed_url', 'width', 'height', 'orientation', 'thumbnail_color', 'provider_id',
            'external_id', 'developer', 'developer_url', 'source_url', 'license_type', 'license_url', 'license_notes',
            'attribution_text', 'hosting_method', 'flash_compatibility', 'difficulty', 'session_length', 'score_mode',
        ])->all());
        $game->slug = $data['slug'] ?? $game->slug ?: Str::slug($data['title']['en']);
        $game->min_age = (int) ($data['min_age'] ?? 0);
        $game->input_types = array_values($data['input_types'] ?? []);
        $game->devices = array_values($data['devices'] ?? []);
        $game->languages = array_values($data['languages'] ?? []);
        foreach (['commercial_use_allowed', 'ads_allowed', 'modifications_allowed', 'thumbnail_rights', 'embed_authorized',
            'is_featured', 'is_editors_pick', 'is_multiplayer', 'is_mobile_friendly'] as $flag) {
            $game->setAttribute($flag, $request->boolean($flag));
        }
        if ($game->engine !== GameEngine::Ruffle) {
            $game->flash_compatibility = null;
        } elseif (! $game->flash_compatibility) {
            $game->flash_compatibility = FlashCompatibility::Untested;
        }
        if ($request->hasFile('thumbnail')) {
            $game->thumbnail_path = app(ImageProcessor::class)->storeCover($request->file('thumbnail'), 'thumbnails');
            // A newly uploaded third-party image needs its usage rights confirmed again.
            if (! $game->is_original && $game->isDirty('thumbnail_path') && ! $request->boolean('thumbnail_rights')) {
                $game->thumbnail_rights = false;
            }
        }
        if ($game->engine === GameEngine::Iframe && ! $game->hosting_method) {
            $game->hosting_method = 'iframe_embed';
        }
    }

    private function syncRelations(Game $game, Request $request): void
    {
        $ids = array_map('intval', $request->input('categories', []));
        $primary = (int) $request->input('primary_category') ?: ($ids[0] ?? null);
        if ($primary && ! in_array($primary, $ids, true)) {
            $ids[] = $primary;
        }
        $game->categories()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['is_primary' => $id === $primary]])->all());

        $tagIds = collect(explode(',', (string) $request->input('tags')))
            ->map(fn ($t) => trim($t))->filter()->unique()->take(20)
            ->map(function ($name) {
                $slug = Str::slug($name);
                if ($slug === '') {
                    return null;
                }

                return Tag::query()->firstOrCreate(['slug' => $slug], ['name' => ['en' => $name]])->id;
            })->filter()->values()->all();
        $game->tags()->sync($tagIds);
        $game->load(['categories', 'tags', 'provider']);
        $game->refreshSearchText();
    }

    public function uploadPackage(Request $request, Game $game, GamePackageInstaller $installer): RedirectResponse
    {
        $request->validate([
            'package' => ['required', 'file', 'max:'.config('platform.uploads.max_game_package_kb'), function ($attr, $file, $fail) {
                if (! in_array(strtolower($file->getClientOriginalExtension()), ['zip', 'swf'], true)) {
                    $fail('Upload a .zip of static game files or a .swf file.');
                }
            }],
        ]);
        $result = $installer->install($game, $request->file('package'));

        $game->entry_path = $result['entry_path'];
        $game->engine = $result['engine'];
        $game->engine_config = $result['engine_config'];
        $game->hosting_method = 'self_hosted';
        $game->launch_status = 'untested';
        if ($game->engine === GameEngine::Ruffle) {
            $game->flash_compatibility = FlashCompatibility::Untested;
        }
        $game->save();
        GameCatalog::flush();
        Audit::log('game.package', $game, ['files' => $result['files'], 'engine' => $game->engine->value, 'entry' => $game->entry_path]);

        return back()->with('status', "Package installed ({$result['files']} files, detected engine: {$game->engine->value}). Run a launch check and test it in the preview.");
    }

    public function check(Game $game, LaunchChecker $checker): RedirectResponse
    {
        $result = $checker->check($game);
        Audit::log('game.check', $game, $result);

        return back()->with($result['ok'] ? 'status' : 'error', 'Launch check: '.$result['message']);
    }

    /** Admin-only preview of any game (including drafts) through the normal sandboxed player. */
    public function preview(Game $game): View
    {
        return view('admin.games.preview', [
            'game' => $game,
            'frameUrl' => PublicGameController::frameUrl($game, preview: true),
        ]);
    }

    public function publish(Request $request, Game $game, GamePublisher $publisher): RedirectResponse
    {
        $data = $request->validate(['publish_at' => ['nullable', 'date']]);
        $at = ! empty($data['publish_at']) ? Carbon::parse($data['publish_at']) : null;
        try {
            $publisher->publish($game, $at);
        } catch (PublishException $e) {
            return back()->with('error', 'Cannot publish: '.implode(' ', $e->blockers));
        }

        return back()->with('status', $game->published_at->isFuture()
            ? 'Scheduled for '.$game->published_at->toDayDateTimeString().'.'
            : 'Published.');
    }

    public function unpublish(Game $game, GamePublisher $publisher): RedirectResponse
    {
        $publisher->unpublish($game);

        return back()->with('status', 'Unpublished.');
    }

    public function rights(Request $request, Game $game): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'rejected', 'unverified'])],
            'confirm' => ['required_if:decision,verified', 'accepted_if:decision,verified'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['confirm.accepted_if' => 'Confirm that you checked the license and source before verifying rights.']);

        if ($data['decision'] === 'verified' && (! $game->license_type || (! $game->is_original && ! $game->source_url))) {
            return back()->with('error', 'Add the license type and source URL before verifying rights.');
        }

        $game->rights_status = RightsStatus::from($data['decision']);
        $game->rights_verified_at = $data['decision'] === 'verified' ? now() : null;
        $game->rights_verified_by = $data['decision'] === 'verified' ? $request->user()->id : null;
        if ($data['decision'] !== 'verified' && $game->status === GameStatus::Published) {
            $game->status = GameStatus::Unpublished;
        }
        if ($data['note']) {
            $game->license_notes = trim(($game->license_notes ? $game->license_notes."\n" : '').'['.now()->toDateString().' '.$request->user()->nickname.'] '.$data['note']);
        }
        $game->save();
        GameCatalog::flush();
        Audit::log('game.rights.'.$data['decision'], $game, ['note' => $data['note']]);

        return back()->with('status', 'Rights status set to '.$data['decision'].'.');
    }

    public function destroy(Request $request, Game $game): RedirectResponse
    {
        if ($game->trashed()) {
            abort_unless($request->user()->hasPermission('games.publish'), 403);
            Audit::log('game.force_delete', $game, ['slug' => $game->slug]);
            $game->forceDelete();
            GameCatalog::flush();

            return redirect()->route('admin.games.index', ['trashed' => 1])->with('status', 'Game permanently deleted. Its uploaded files remain on disk until cleaned up.');
        }
        $game->status = GameStatus::Archived;
        $game->save();
        $game->delete();
        GameCatalog::flush();
        Audit::log('game.delete', $game);

        return redirect()->route('admin.games.index')->with('status', 'Game moved to trash.');
    }

    public function restore(Game $game): RedirectResponse
    {
        $game->restore();
        $game->status = GameStatus::Draft;
        $game->save();
        Audit::log('game.restore', $game);

        return redirect()->route('admin.games.edit', $game)->with('status', 'Game restored as a draft.');
    }

    public function bulk(Request $request, GamePublisher $publisher, LaunchChecker $checker): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['publish', 'unpublish', 'feature', 'unfeature', 'check', 'trash', 'category'])],
            'category_id' => ['required_if:action,category', 'nullable', 'exists:categories,id'],
        ]);
        if (in_array($data['action'], ['publish', 'unpublish'], true)) {
            abort_unless($request->user()->hasPermission('games.publish'), 403);
        }

        $games = Game::query()->whereIn('id', $data['ids'])->get();
        $ok = 0;
        $failed = [];
        foreach ($games as $game) {
            try {
                match ($data['action']) {
                    'publish' => $publisher->publish($game),
                    'unpublish' => $publisher->unpublish($game),
                    'feature' => $game->forceFill(['is_featured' => true])->save(),
                    'unfeature' => $game->forceFill(['is_featured' => false])->save(),
                    'check' => $checker->check($game)['ok'] ?: throw new \RuntimeException($game->last_check_message),
                    'trash' => $game->forceFill(['status' => GameStatus::Archived])->save() && $game->delete(),
                    'category' => $game->categories()->syncWithoutDetaching([(int) $data['category_id'] => ['is_primary' => false]]),
                };
                $ok++;
            } catch (PublishException $e) {
                $failed[] = $game->tr('title', 'en').': '.implode(' ', $e->blockers);
            } catch (\RuntimeException $e) {
                $failed[] = $game->tr('title', 'en').': '.$e->getMessage();
            }
        }
        GameCatalog::flush();
        Audit::log('game.bulk.'.$data['action'], null, ['ids' => $games->pluck('id')->all(), 'ok' => $ok, 'failed' => count($failed)]);

        $redirect = back()->with('status', "{$data['action']}: $ok of {$games->count()} games updated.");

        return $failed ? $redirect->with('error', implode(' | ', array_slice($failed, 0, 10))) : $redirect;
    }
}
