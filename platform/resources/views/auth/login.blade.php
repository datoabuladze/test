@extends('layouts.app')
@section('content')
<x-auth-card :title="__('Welcome back')" :subtitle="__('Log in to save favorites, scores and achievements.')">
    <form method="post" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <x-field name="email" type="email" :label="__('Email')" required autocomplete="email" autofocus/>
        <x-field name="password" type="password" :label="__('Password')" required autocomplete="current-password"/>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-ink-2"><input type="checkbox" name="remember" class="rounded border-line bg-bg-2"> {{ __('Remember me') }}</label>
            <a href="{{ route('password.request') }}" class="text-brand-2 hover:underline">{{ __('Forgot password?') }}</a>
        </div>
        <button class="btn-primary w-full py-3">{{ __('Log in') }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-ink-2">{{ __('New here?') }} <a href="{{ route('register') }}" class="font-semibold text-brand-2 hover:underline">{{ __('Create a free account') }}</a></p>
</x-auth-card>
@endsection
