@extends('layouts.app')
@section('content')
<x-auth-card :title="__('Create your account')" :subtitle="__('Free forever. We only ask for what we need.')">
    <form method="post" action="{{ route('register.store') }}" class="space-y-4">
        @csrf
        <x-field name="nickname" :label="__('Nickname')" required autocomplete="username" :help="__('Shown on leaderboards. 3–24 letters, numbers, dots, dashes or underscores.')"/>
        <x-field name="email" type="email" :label="__('Email')" required autocomplete="email"/>
        <x-field name="password" type="password" :label="__('Password')" required autocomplete="new-password" :help="__('At least 10 characters with letters and numbers.')"/>
        <x-field name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password"/>
        <label class="flex items-start gap-2 text-sm text-ink-2">
            <input type="checkbox" name="terms" value="1" class="mt-1 rounded border-line bg-bg-2" required>
            <span>{!! __('I agree to the :terms and :privacy.', [
                'terms' => '<a class="text-brand-2 underline" href="'.e(route('pages.show', 'terms')).'">'.e(__('Terms of use')).'</a>',
                'privacy' => '<a class="text-brand-2 underline" href="'.e(route('pages.show', 'privacy')).'">'.e(__('Privacy policy')).'</a>',
            ]) !!}</span>
        </label>
        @error('terms')<p class="error">{{ $message }}</p>@enderror
        <button class="btn-primary w-full py-3">{{ __('Create account') }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-ink-2">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-semibold text-brand-2 hover:underline">{{ __('Log in') }}</a></p>
</x-auth-card>
@endsection
