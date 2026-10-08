@extends('layouts.admin')
@section('title', $game->exists ? 'Edit · '.$game->tr('title', 'en') : 'New game')

@php
    $user = auth()->user();
    $selectedCats = old('categories', $game->exists ? $game->categories->pluck('id')->all() : []);
    $primaryCat = old('primary_category', $game->exists ? $game->categories->firstWhere('pivot.is_primary', true)?->id : null);
    $checked = fn (string $field, string $value) => in_array($value, old($field, $game->getAttribute($field) ?? []), true);
    $flag = fn (string $field) => (bool) old($field, $game->getAttribute($field));
@endphp

@section('content')
<div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_360px]">
<form method="post" enctype="multipart/form-data" action="{{ $game->exists ? route('admin.games.update', $game) : route('admin.games.store') }}" class="space-y-6" x-data="engineFields">
    @csrf
    @if ($game->exists) @method('put') @endif

    <section class="card space-y-4 p-5" x-data="slugger">
        <h2 class="font-bold">Basics</h2>
        <x-admin.translatable name="title" label="Title" :model="$game" required/>
        <div>
            <label class="label" for="slug">URL slug</label>
            <input id="slug" name="slug" value="{{ old('slug', $game->slug) }}" class="input font-mono" data-slug pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="120">
            <p class="help">Lowercase letters, digits and dashes. Leave empty to generate from the English title. Changing it on a published game breaks old links unless you add a redirect.</p>
        </div>
        <x-admin.translatable name="short_description" label="Short description (cards, meta)" :model="$game"/>
        <x-admin.translatable name="description" label="Description" :model="$game" textarea :rows="5"/>
        <x-admin.translatable name="instructions" label="How to play" :model="$game" textarea :rows="3"/>
        <x-admin.translatable name="controls" label="Controls" :model="$game" textarea :rows="2"/>
    </section>

    <section class="card space-y-4 p-5">
        <h2 class="font-bold">Engine & files</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="engine">Engine</label>
                <select id="engine" name="engine" class="input">
                    @foreach (\App\Enums\GameEngine::cases() as $e)
                        <option value="{{ $e->value }}" @selected(old('engine', $game->engine?->value) === $e->value)>{{ $e->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="orientation">Orientation</label>
                <select id="orientation" name="orientation" class="input">
                    @foreach (['any' => 'Any', 'landscape' => 'Landscape', 'portrait' => 'Portrait'] as $k => $l)
                        <option value="{{ $k }}" @selected(old('orientation', $game->orientation) === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label" for="width">Width</label><input id="width" type="number" name="width" value="{{ old('width', $game->width) }}" class="input" min="200" max="4096"></div>
                <div><label class="label" for="height">Height</label><input id="height" type="number" name="height" value="{{ old('height', $game->height) }}" class="input" min="200" max="4096"></div>
            </div>
        </div>
        <div x-show="isLocal">
            <label class="label" for="entry_path">Entry path</label>
            <input id="entry_path" name="entry_path" value="{{ old('entry_path', $game->entry_path) }}" class="input font-mono" placeholder="game-files/12-abc/index.html">
            <p class="help">Set automatically when you upload a package. Relative to the public games directory (games/… or game-files/…).</p>
        </div>
        <div x-cloak x-show="isEmbed">
            <label class="label" for="embed_url">Authorized embed URL (HTTPS)</label>
            <input id="embed_url" type="url" name="embed_url" value="{{ old('embed_url', $game->embed_url) }}" class="input font-mono" placeholder="https://…">
            <p class="help">Only for providers that explicitly allow embedding. The host must be on the provider's allow-list.</p>
        </div>
        <div x-cloak x-show="isRuffle">
            <label class="label" for="flash_compatibility">Flash compatibility (Ruffle)</label>
            <select id="flash_compatibility" name="flash_compatibility" class="input">
                @foreach (\App\Enums\FlashCompatibility::cases() as $f)
                    <option value="{{ $f->value }}" @selected(old('flash_compatibility', $game->flash_compatibility?->value ?? 'untested') === $f->value)>{{ $f->label() }}</option>
                @endforeach
            </select>
            <p class="help">Set this after testing the game in the preview. Only Compatible and Partially compatible games can be published.</p>
        </div>
        <div>
            <label class="label" for="score_mode">Leaderboard</label>
            <select id="score_mode" name="score_mode" class="input">
                @foreach (['none' => 'No leaderboard', 'casual' => 'Casual (plausibility checks only)', 'verified' => 'Verified (server replay; needs a verifier)'] as $k => $l)
                    <option value="{{ $k }}" @selected(old('score_mode', $game->score_mode ?? 'none') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </section>

    <section class="card space-y-4 p-5">
        <h2 class="font-bold">Discovery</h2>
        <div>
            <span class="label">Categories</span>
            <div class="grid max-h-64 gap-1 overflow-y-auto rounded-xl border border-line p-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $c)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="categories[]" value="{{ $c->id }}" @checked(in_array($c->id, array_map('intval', $selectedCats), true))>{{ $c->parent_id ? '— ' : '' }}{{ $c->tr('name', 'en') }}</label>
                @endforeach
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="primary_category">Primary category</label>
                <select id="primary_category" name="primary_category" class="input">
                    <option value="">First selected</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) $primaryCat === $c->id)>{{ $c->tr('name', 'en') }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="tags">Tags</label>
                <input id="tags" name="tags" value="{{ old('tags', $game->exists ? $game->tags->pluck('slug')->join(', ') : '') }}" class="input" placeholder="retro, physics, relaxing">
                <p class="help">Comma separated. New tags are created automatically.</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <fieldset><legend class="label">Input</legend>
                @foreach (['keyboard', 'mouse', 'touch', 'gamepad'] as $v)<label class="mr-3 inline-flex items-center gap-1.5 text-sm"><input type="checkbox" name="input_types[]" value="{{ $v }}" @checked($checked('input_types', $v))>{{ ucfirst($v) }}</label>@endforeach
            </fieldset>
            <fieldset><legend class="label">Devices</legend>
                @foreach (['desktop', 'tablet', 'mobile'] as $v)<label class="mr-3 inline-flex items-center gap-1.5 text-sm"><input type="checkbox" name="devices[]" value="{{ $v }}" @checked($checked('devices', $v))>{{ ucfirst($v) }}</label>@endforeach
            </fieldset>
            <fieldset><legend class="label">Game languages</legend>
                @foreach (array_keys(config('platform.locales')) as $v)<label class="mr-3 inline-flex items-center gap-1.5 text-sm"><input type="checkbox" name="languages[]" value="{{ $v }}" @checked($checked('languages', $v))>{{ strtoupper($v) }}</label>@endforeach
            </fieldset>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label" for="difficulty">Difficulty</label>
                <select id="difficulty" name="difficulty" class="input"><option value="">—</option>@foreach (['easy', 'medium', 'hard'] as $v)<option value="{{ $v }}" @selected(old('difficulty', $game->difficulty) === $v)>{{ ucfirst($v) }}</option>@endforeach</select></div>
            <div><label class="label" for="session_length">Session length</label>
                <select id="session_length" name="session_length" class="input"><option value="">—</option>@foreach (['short', 'medium', 'long'] as $v)<option value="{{ $v }}" @selected(old('session_length', $game->session_length) === $v)>{{ ucfirst($v) }}</option>@endforeach</select></div>
            <div><label class="label" for="min_age">Minimum age</label><input id="min_age" type="number" name="min_age" min="0" max="18" value="{{ old('min_age', $game->min_age ?? 0) }}" class="input"></div>
        </div>
        <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
            @foreach (['is_featured' => 'Featured (hero)', 'is_editors_pick' => "Editor's pick", 'is_multiplayer' => 'Multiplayer', 'is_mobile_friendly' => 'Mobile friendly'] as $f => $l)
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="{{ $f }}" value="1" @checked($flag($f))>{{ $l }}</label>
            @endforeach
        </div>
    </section>

    <section class="card space-y-4 p-5">
        <div class="flex items-center justify-between"><h2 class="font-bold">Source & license</h2><x-admin.status :value="$game->rights_status"/></div>
        @if ($game->rights_status === \App\Enums\RightsStatus::Verified)
            <p class="rounded-lg bg-warn/10 px-3 py-2 text-xs text-warn">Rights are verified. Changing any licensing field below sends the game back to rights review and hides it until it is verified again.</p>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="provider_id">Provider</label>
                <select id="provider_id" name="provider_id" class="input"><option value="">None (own or manual)</option>
                    @foreach ($providers as $p)<option value="{{ $p->id }}" @selected((string) old('provider_id', $game->provider_id) === (string) $p->id)>{{ $p->name }}</option>@endforeach
                </select></div>
            <div><label class="label" for="external_id">Provider game ID</label><input id="external_id" name="external_id" value="{{ old('external_id', $game->external_id) }}" class="input font-mono"></div>
            <div><label class="label" for="developer">Developer</label><input id="developer" name="developer" value="{{ old('developer', $game->developer) }}" class="input"></div>
            <div><label class="label" for="developer_url">Developer URL</label><input id="developer_url" type="url" name="developer_url" value="{{ old('developer_url', $game->developer_url) }}" class="input"></div>
            <div><label class="label" for="source_url">Source URL</label><input id="source_url" type="url" name="source_url" value="{{ old('source_url', $game->source_url) }}" class="input"><p class="help">Where the game and its license were obtained.</p></div>
            <div><label class="label" for="license_type">License type</label><input id="license_type" name="license_type" value="{{ old('license_type', $game->license_type) }}" class="input" list="license-types" placeholder="MIT, CC-BY-4.0, Provider agreement…">
                <datalist id="license-types">@foreach (['Original', 'MIT', 'Apache-2.0', 'BSD-3-Clause', 'GPL-3.0', 'CC0-1.0', 'CC-BY-4.0', 'CC-BY-SA-4.0', 'Provider agreement', 'Written permission'] as $l)<option value="{{ $l }}">@endforeach</datalist></div>
            <div><label class="label" for="license_url">License URL</label><input id="license_url" type="url" name="license_url" value="{{ old('license_url', $game->license_url) }}" class="input"></div>
            <div><label class="label" for="hosting_method">Permitted hosting</label>
                <select id="hosting_method" name="hosting_method" class="input"><option value="">—</option>
                    @foreach (['self_hosted' => 'Self-hosted files', 'iframe_embed' => 'Embed from provider'] as $k => $l)<option value="{{ $k }}" @selected(old('hosting_method', $game->hosting_method) === $k)>{{ $l }}</option>@endforeach
                </select></div>
        </div>
        <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
            @foreach (['commercial_use_allowed' => 'Commercial use allowed', 'ads_allowed' => 'Ads allowed around the game', 'modifications_allowed' => 'Modifications allowed', 'thumbnail_rights' => 'Thumbnail usage allowed', 'embed_authorized' => 'Embedding authorized'] as $f => $l)
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="{{ $f }}" value="1" @checked($flag($f))>{{ $l }}</label>
            @endforeach
        </div>
        <div><label class="label" for="attribution_text">Attribution / credits shown on the game page</label><textarea id="attribution_text" name="attribution_text" rows="2" class="input">{{ old('attribution_text', $game->attribution_text) }}</textarea></div>
        <div><label class="label" for="license_notes">Internal license notes</label><textarea id="license_notes" name="license_notes" rows="3" class="input">{{ old('license_notes', $game->license_notes) }}</textarea></div>
    </section>

    <section class="card space-y-4 p-5">
        <h2 class="font-bold">Thumbnail & SEO</h2>
        <div class="grid gap-4 sm:grid-cols-[160px_1fr]">
            <div class="aspect-[4/3] overflow-hidden rounded-xl bg-card-2">@if ($game->exists)<x-game-thumb :game="$game" sizes="160px"/>@endif</div>
            <div class="space-y-3">
                <div><label class="label" for="thumbnail">Upload thumbnail</label><input id="thumbnail" type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp,image/gif" class="input">
                    <p class="help">Re-encoded to 640×480 WebP. Only upload images you have the right to use.</p></div>
                <div><label class="label" for="thumbnail_color">Fallback color</label><input id="thumbnail_color" name="thumbnail_color" value="{{ old('thumbnail_color', $game->thumbnail_color) }}" class="input w-32 font-mono" placeholder="#7c5cff"></div>
            </div>
        </div>
        <x-admin.translatable name="seo_title" label="SEO title (optional, max 70)" :model="$game"/>
        <x-admin.translatable name="seo_description" label="SEO description (optional, max 170)" :model="$game"/>
    </section>

    <div class="sticky bottom-0 z-10 -mx-1 flex gap-2 rounded-xl border border-line bg-bg/90 p-3 backdrop-blur">
        <button class="btn-primary"><x-icon name="check" class="size-4"/>{{ $game->exists ? 'Save changes' : 'Create draft' }}</button>
        <a href="{{ route('admin.games.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>

@if ($game->exists)
<aside class="space-y-4">
    <section class="card space-y-3 p-4">
        <div class="flex items-center justify-between"><h2 class="font-bold">Publishing</h2><x-admin.status :value="$game->status"/></div>
        @if ($game->status === \App\Enums\GameStatus::Published && $game->published_at?->isFuture())
            <p class="text-sm text-warn">Scheduled for {{ $game->published_at->toDayDateTimeString() }}</p>
        @endif
        @if ($blockers)
            <div class="rounded-lg bg-bad/10 p-3 text-xs text-bad"><p class="mb-1 font-semibold">Not publishable yet:</p><ul class="list-disc space-y-0.5 pl-4">@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul></div>
        @else
            <p class="rounded-lg bg-ok/10 p-3 text-xs text-ok">All publishing checks pass.</p>
        @endif
        @if ($user->hasPermission('games.publish'))
            <form method="post" action="{{ route('admin.games.publish', $game) }}" class="space-y-2">
                @csrf
                <label class="label" for="publish_at">Publish at (optional, server time {{ config('app.timezone') }})</label>
                <input id="publish_at" type="datetime-local" name="publish_at" class="input">
                <button class="btn-primary w-full" @disabled($blockers)><x-icon name="bolt" class="size-4"/>Publish or schedule</button>
            </form>
            @if (in_array($game->status, [\App\Enums\GameStatus::Published], true))
                <form method="post" action="{{ route('admin.games.unpublish', $game) }}">@csrf<button class="btn-ghost w-full">Unpublish</button></form>
            @endif
        @endif
        <div class="flex gap-2">
            <a href="{{ route('admin.games.preview', $game) }}" class="btn-ghost flex-1"><x-icon name="eye" class="size-4"/>Preview</a>
            @if ($game->isPublic())<a href="{{ $game->url('en') }}" class="btn-ghost flex-1" target="_blank" rel="noopener"><x-icon name="external" class="size-4"/>Live page</a>@endif
        </div>
    </section>

    @if ($user->hasPermission('games.rights'))
    <section class="card space-y-3 p-4">
        <div class="flex items-center justify-between"><h2 class="font-bold">Rights review</h2><x-admin.status :value="$game->rights_status"/></div>
        @if ($game->rights_verified_at)<p class="text-xs text-ink-3">Verified {{ $game->rights_verified_at->toDayDateTimeString() }}</p>@endif
        <form method="post" action="{{ route('admin.games.rights', $game) }}" class="space-y-2">
            @csrf
            <select name="decision" class="input">
                <option value="verified">Verified: rights confirmed</option>
                <option value="rejected">Rejected: not allowed</option>
                <option value="unverified">Back to unverified</option>
            </select>
            <label class="flex items-start gap-2 text-xs text-ink-2"><input type="checkbox" name="confirm" value="1" class="mt-0.5">I checked the license, the source and the permitted hosting method for this game and its thumbnail.</label>
            <textarea name="note" rows="2" class="input" placeholder="Note for the audit trail (optional)"></textarea>
            <button class="btn-ghost w-full"><x-icon name="shield" class="size-4"/>Record decision</button>
        </form>
    </section>
    @endif

    <section class="card space-y-3 p-4">
        <div class="flex items-center justify-between"><h2 class="font-bold">Files & launch check</h2><x-admin.status :value="$game->launch_status"/></div>
        @if ($game->last_check_message)<p class="text-xs text-ink-2">{{ $game->last_check_message }} <span class="text-ink-3">({{ $game->last_checked_at?->diffForHumans() }})</span></p>@endif
        <form method="post" action="{{ route('admin.games.check', $game) }}">@csrf<button class="btn-ghost w-full"><x-icon name="refresh" class="size-4"/>Run launch check</button></form>
        @if ($game->engine !== \App\Enums\GameEngine::Iframe && ! $game->is_original)
            <form method="post" action="{{ route('admin.games.package', $game) }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <label class="label" for="package">Upload package (.zip of static files or .swf)</label>
                <input id="package" type="file" name="package" accept=".zip,.swf" class="input" required>
                <p class="help">Max {{ number_format(config('platform.uploads.max_game_package_kb') / 1024) }} MB. Only static asset types are extracted; server-side code is rejected.</p>
                <button class="btn-ghost w-full"><x-icon name="upload" class="size-4"/>Install package</button>
            </form>
        @endif
    </section>

    <section class="card space-y-2 p-4">
        @if ($game->trashed())
            <form method="post" action="{{ route('admin.games.restore', $game) }}">@csrf<button class="btn-ghost w-full">Restore as draft</button></form>
            @if ($user->hasPermission('games.publish'))
                <form method="post" action="{{ route('admin.games.destroy', $game) }}" x-data="confirmForm" data-confirm="Permanently delete this game and its play history? This cannot be undone." @submit="confirmSubmit">
                    @csrf @method('delete')
                    <button class="btn-danger w-full"><x-icon name="trash" class="size-4"/>Delete permanently</button>
                </form>
            @endif
        @else
            <form method="post" action="{{ route('admin.games.destroy', $game) }}" x-data="confirmForm" data-confirm="Move this game to the trash? It will be hidden from the site." @submit="confirmSubmit">
                @csrf @method('delete')
                <button class="btn-danger w-full"><x-icon name="trash" class="size-4"/>Move to trash</button>
            </form>
        @endif
    </section>
</aside>
@endif
</div>
@endsection
