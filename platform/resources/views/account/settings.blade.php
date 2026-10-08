@extends('layouts.app')
@section('content')
<div class="container-page max-w-3xl pt-8">
    @include('account.nav')
    <div class="space-y-6">
        <section class="card p-6">
            <h2 class="mb-4 text-lg font-bold">{{ __('Profile') }}</h2>
            <div class="mb-6 flex items-center gap-4">
                <div class="flex size-16 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-brand to-brand-3 text-2xl font-black text-white">
                    @if ($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="" class="size-full object-cover">@else{{ $user->initials() }}@endif
                </div>
                <form method="post" action="{{ route('account.avatar') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="text-sm text-ink-2 file:mr-3 file:rounded-lg file:border-0 file:bg-card-2 file:px-3 file:py-2 file:text-ink" required>
                    <button class="btn-ghost btn-sm">{{ __('Upload') }}</button>
                    @error('avatar')<p class="error w-full">{{ $message }}</p>@enderror @error('image')<p class="error w-full">{{ $message }}</p>@enderror
                </form>
            </div>
            <form method="post" action="{{ route('account.settings.update') }}" class="space-y-4">
                @csrf @method('put')
                <x-field name="nickname" :label="__('Nickname')" :value="$user->nickname" required/>
                <x-field name="email" type="email" :label="__('Email')" :value="$user->email" required/>
                <div>
                    <label class="label" for="f-locale">{{ __('Language') }}</label>
                    <select id="f-locale" name="locale" class="input">
                        @foreach (config('platform.locales') as $code => $meta)<option value="{{ $code }}" @selected($user->locale === $code)>{{ $meta['native'] }}</option>@endforeach
                    </select>
                </div>
                <h3 class="pt-2 text-sm font-semibold">{{ __('Privacy') }}</h3>
                <label class="flex items-start gap-3 text-sm"><input type="hidden" name="profile_public" value="0"><input type="checkbox" name="profile_public" value="1" class="mt-1" @checked($user->profile_public)>
                    <span>{{ __('Public profile') }}<span class="block text-xs text-ink-3">{{ __('Show my nickname, level and favorites on my profile page and leaderboards.') }}</span></span></label>
                <label class="flex items-start gap-3 text-sm"><input type="hidden" name="personalization_enabled" value="0"><input type="checkbox" name="personalization_enabled" value="1" class="mt-1" @checked($user->personalization_enabled)>
                    <span>{{ __('Personalized recommendations') }}<span class="block text-xs text-ink-3">{{ __('Use my favorites and recent plays to suggest games. Nothing is shared with third parties.') }}</span></span></label>
                <h3 class="pt-2 text-sm font-semibold">{{ __('Notifications') }}</h3>
                <label class="flex items-center gap-3 text-sm"><input type="hidden" name="notify_news" value="0"><input type="checkbox" name="notify_news" value="1" @checked($user->notification_preferences['news'] ?? false)>{{ __('Email me about new games (rarely)') }}</label>
                <label class="flex items-center gap-3 text-sm"><input type="hidden" name="notify_achievements" value="0"><input type="checkbox" name="notify_achievements" value="1" @checked($user->notification_preferences['achievements'] ?? true)>{{ __('Show achievement notifications') }}</label>
                <button class="btn-primary">{{ __('Save') }}</button>
            </form>
        </section>
        <section class="card p-6">
            <h2 class="mb-4 text-lg font-bold">{{ __('Change password') }}</h2>
            <form method="post" action="{{ route('account.password') }}" class="space-y-4">
                @csrf @method('put')
                <x-field name="current_password" type="password" :label="__('Current password')" required autocomplete="current-password"/>
                <x-field name="password" type="password" :label="__('New password')" required autocomplete="new-password"/>
                <x-field name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password"/>
                <button class="btn-primary">{{ __('Update password') }}</button>
            </form>
        </section>
        <section class="card border-bad/30 p-6">
            <h2 class="text-lg font-bold text-bad">{{ __('Delete account') }}</h2>
            <p class="mt-1 text-sm text-ink-2">{{ __('This permanently deletes your account, favorites, ratings, scores and achievements. It cannot be undone.') }}</p>
            <form method="post" action="{{ route('account.destroy') }}" class="mt-4 flex flex-wrap items-end gap-3" x-data="confirmForm" data-confirm="{{ __('Delete your account permanently?') }}" @submit="confirmSubmit">
                @csrf @method('delete')
                <div class="min-w-60 flex-1"><x-field name="delete_password" type="password" :label="__('Confirm with your password')" required autocomplete="current-password"/></div>
                <button class="btn-danger">{{ __('Delete my account') }}</button>
            </form>
        </section>
    </div>
</div>
@endsection
