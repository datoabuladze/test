@extends('layouts.admin')
@section('title', $provider->exists ? 'Edit provider · '.$provider->name : 'New provider')

@php $s = $provider->settings ?? []; @endphp
@section('content')
<form method="post" action="{{ $provider->exists ? route('admin.providers.update', $provider) : route('admin.providers.store') }}" class="max-w-3xl space-y-6">
    @csrf
    @if ($provider->exists) @method('put') @endif
    <section class="card grid gap-4 p-5 sm:grid-cols-2">
        <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name', $provider->name) }}" class="input" required></div>
        <div><label class="label" for="slug">Slug</label><input id="slug" name="slug" value="{{ old('slug', $provider->slug) }}" class="input font-mono" required></div>
        <div><label class="label" for="adapter">Import adapter</label>
            <select id="adapter" name="adapter" class="input">
                @foreach (config('platform.import.adapters') as $key => $class)<option value="{{ $key }}" @selected(old('adapter', $provider->adapter) === $key)>{{ app($class)->label() }}</option>@endforeach
            </select></div>
        <div><label class="label" for="website_url">Website</label><input id="website_url" type="url" name="website_url" value="{{ old('website_url', $provider->website_url) }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label" for="allowed_embed_hosts">Allowed embed hosts</label>
            <textarea id="allowed_embed_hosts" name="allowed_embed_hosts" rows="2" class="input font-mono" placeholder="html5.example.com, *.cdn.example.com">{{ old('allowed_embed_hosts', implode(', ', $provider->allowed_embed_hosts ?? [])) }}</textarea>
            <p class="help">Only these hosts may appear in embed URLs for this provider's games. <code>*.example.com</code> matches subdomains.</p></div>
        <div class="sm:col-span-2"><label class="label" for="feed_url">Catalog feed URL (HTTPS, for feed adapters)</label><input id="feed_url" type="url" name="feed_url" value="{{ old('feed_url', $s['feed_url'] ?? '') }}" class="input font-mono">
            <p class="help">Do not put API secrets here; keep credentials in the server environment.</p></div>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $provider->is_active))>Active</label>
    </section>
    <section class="card space-y-4 p-5">
        <h2 class="font-bold">Agreement & licensing defaults</h2>
        <p class="text-sm text-ink-3">These defaults fill blank licensing fields on imported rows. They must reflect what the agreement actually allows. Imported games still need individual rights review.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="agreement_url">Agreement / terms URL</label><input id="agreement_url" type="url" name="agreement_url" value="{{ old('agreement_url', $provider->agreement_url) }}" class="input"></div>
            <div><label class="label" for="license_type">Default license type</label><input id="license_type" name="license_type" value="{{ old('license_type', $s['license_type'] ?? '') }}" class="input" placeholder="Provider agreement"></div>
            <div><label class="label" for="license_url">Default license URL</label><input id="license_url" type="url" name="license_url" value="{{ old('license_url', $s['license_url'] ?? '') }}" class="input"></div>
            <div><label class="label" for="hosting_method">Default hosting</label>
                <select id="hosting_method" name="hosting_method" class="input"><option value="">—</option>@foreach (['iframe_embed' => 'Embed from provider', 'self_hosted' => 'Self-hosted files'] as $k => $l)<option value="{{ $k }}" @selected(old('hosting_method', $s['hosting_method'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        </div>
        <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
            @foreach (['commercial_use_allowed' => 'Commercial use allowed', 'ads_allowed' => 'Ads allowed', 'modifications_allowed' => 'Modifications allowed', 'thumbnail_rights' => 'Thumbnails may be used', 'embed_authorized' => 'Embedding authorized'] as $f => $l)
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="{{ $f }}" value="1" @checked(old($f, $s[$f] ?? false))>{{ $l }}</label>
            @endforeach
        </div>
        <div><label class="label" for="agreement_notes">Agreement notes</label><textarea id="agreement_notes" name="agreement_notes" rows="4" class="input">{{ old('agreement_notes', $provider->agreement_notes) }}</textarea></div>
    </section>
    <div class="flex gap-2"><button class="btn-primary">Save</button><a href="{{ route('admin.providers.index') }}" class="btn-ghost">Cancel</a></div>
</form>
@if ($provider->exists)
    <form method="post" action="{{ route('admin.providers.destroy', $provider) }}" class="mt-6" x-data="confirmForm" data-confirm="Delete this provider?" @submit="confirmSubmit">@csrf @method('delete')<button class="btn-danger btn-sm">Delete provider</button></form>
@endif
@endsection
