{{-- Campaign form. $campaign may be a new model; $fresh = true repopulates from old() input. --}}
@php
    $val = fn ($key, $default = null) => $fresh ? old($key, $default) : ($campaign->{$key} ?? $default);
    $dt = fn ($key) => $fresh ? old($key) : $campaign->{$key}?->format('Y-m-d\TH:i');
@endphp
<form method="post" enctype="multipart/form-data"
      action="{{ $campaign->exists ? route('admin.ads.update', $campaign) : route('admin.ads.store') }}" class="space-y-4">
    @csrf
    @if ($campaign->exists) @method('PUT') @endif
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block sm:col-span-2"><span class="label">Name</span>
            <input name="name" value="{{ $val('name') }}" class="input" required maxlength="120"></label>
        <label class="block"><span class="label">Type</span>
            <select name="type" class="input">
                @foreach ($types as $k => $label)<option value="{{ $k }}" @selected($val('type', 'direct') === $k)>{{ $label }}</option>@endforeach
            </select></label>
        <label class="block"><span class="label">Placement</span>
            <select name="ad_placement_id" class="input" required>
                @foreach ($placements as $p)<option value="{{ $p->id }}" @selected((int) $val('ad_placement_id') === $p->id)>{{ $p->name }}</option>@endforeach
            </select></label>
    </div>

    <fieldset class="space-y-3 rounded-xl border border-line p-3">
        <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-ink-3">Direct banner</legend>
        @if ($campaign->image_path)
            <img src="{{ asset('storage/'.$campaign->image_path) }}" alt="{{ $campaign->alt_text }}" class="max-h-20 w-auto rounded-lg">
        @endif
        <label class="block"><span class="label">Image</span>
            <input type="file" name="image" accept="image/png,image/jpeg,image/webp" class="input">
            <span class="help block">PNG, JPG or WebP, max 1&nbsp;MB.{{ $campaign->image_path ? ' Leave empty to keep the current image.' : '' }}</span></label>
        <label class="block"><span class="label">Target URL</span>
            <input type="url" name="target_url" value="{{ $val('target_url') }}" class="input" placeholder="https://…" pattern="https://.*" maxlength="1024"></label>
        <label class="block"><span class="label">Alt text</span>
            <input name="alt_text" value="{{ $val('alt_text') }}" class="input" maxlength="160"></label>
    </fieldset>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block"><span class="label">AdSense slot ID <span class="text-ink-3">(AdSense)</span></span>
            <input name="adsense_slot" value="{{ $val('adsense_slot') }}" class="input font-mono" inputmode="numeric" maxlength="20"></label>
        <label class="block"><span class="label">Game <span class="text-ink-3">(Sponsored game)</span></span>
            <select name="game_id" class="input">
                <option value="">—</option>
                @foreach ($games as $g)<option value="{{ $g->id }}" @selected((int) $val('game_id') === $g->id)>{{ $g->tr('title', 'en') }}</option>@endforeach
            </select></label>
        <label class="block"><span class="label">Starts</span><input type="datetime-local" name="starts_at" value="{{ $dt('starts_at') }}" class="input"></label>
        <label class="block"><span class="label">Ends</span><input type="datetime-local" name="ends_at" value="{{ $dt('ends_at') }}" class="input"></label>
        <label class="block"><span class="label">Priority</span><input type="number" name="priority" value="{{ $val('priority', 0) }}" min="0" max="1000" class="input">
            <span class="help block">Higher wins when several campaigns run in one placement.</span></label>
        <label class="mt-7 flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($fresh ? old('is_active', true) : $campaign->is_active) class="rounded border-line bg-bg-2"> Active</label>
    </div>
    <button class="btn-primary {{ $campaign->exists ? 'btn-sm' : 'w-full' }}"><x-icon name="check" class="size-4"/>{{ $campaign->exists ? 'Save campaign' : 'Create campaign' }}</button>
</form>
