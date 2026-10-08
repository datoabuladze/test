@extends('layouts.admin')
@section('title', 'Settings')
@section('content')
<form method="post" action="{{ route('admin.settings.update') }}" class="grid max-w-5xl gap-6 lg:grid-cols-2">
    @csrf
    <section class="card space-y-4 p-5 lg:col-span-2">
        <h2 class="font-semibold">Announcement banner</h2>
        @php $values = old('announcement', $announcement); @endphp
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($locales as $code => $meta)
                <label class="block">
                    <span class="mb-0.5 block text-[11px] font-semibold uppercase tracking-wide text-ink-3">{{ $code }} · {{ $meta['name'] }}</span>
                    <input name="announcement[{{ $code }}]" value="{{ $values[$code] ?? '' }}" class="input" lang="{{ $code }}" maxlength="300">
                </label>
            @endforeach
        </div>
        <p class="help">Short site-wide message (e.g. planned maintenance). Leave every language empty to hide it. Languages without text fall back to English.</p>
    </section>

    <section class="card space-y-4 p-5">
        <h2 class="font-semibold">Accounts</h2>
        <label class="flex items-start gap-3">
            <input type="checkbox" name="registration_open" value="1" @checked(old('registration_open', $registrationOpen)) class="mt-0.5 rounded border-line bg-bg-2">
            <span><span class="block text-sm font-medium">Registration open</span>
                <span class="help block">When off, the sign-up form shows a “registration is closed” message. Existing users can still log in.</span></span>
        </label>
    </section>

    <section class="card space-y-3 p-5">
        <h2 class="font-semibold">Environment <span class="text-xs font-normal text-ink-3">(read-only)</span></h2>
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
            <dt class="text-ink-3">Brand</dt><dd>{{ config('platform.brand') }}</dd>
            <dt class="text-ink-3">Default language</dt><dd>{{ $locales[$defaultLocale]['name'] ?? $defaultLocale }} <span class="font-mono text-xs text-ink-3">({{ $defaultLocale }})</span></dd>
            <dt class="text-ink-3">Languages</dt><dd>{{ implode(', ', array_map(fn ($m) => $m['name'], $locales)) }}</dd>
            <dt class="text-ink-3">GA4 ID</dt><dd>@if ($ga4)<span class="font-mono">{{ $ga4 }}</span>@else<span class="text-ink-3">not set</span>@endif</dd>
        </dl>
        <p class="help">Change these via environment variables (PLATFORM_BRAND, GA4_MEASUREMENT_ID) and redeploy.</p>
    </section>

    <section class="card space-y-4 p-5 lg:col-span-2">
        <h2 class="font-semibold">Social links</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($socialNetworks as $key => [$label, $hosts])
                <div>
                    <label class="label" for="f-social-{{ $key }}">{{ $label }}</label>
                    <input id="f-social-{{ $key }}" type="url" name="social[{{ $key }}]" value="{{ old("social.$key", $social[$key] ?? '') }}" class="input" placeholder="https://{{ $hosts[0] }}/…" maxlength="255">
                    @error("social.$key")<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    </section>

    <div class="lg:col-span-2">
        <button class="btn-primary"><x-icon name="check" class="size-4"/>Save settings</button>
    </div>
</form>
@endsection
