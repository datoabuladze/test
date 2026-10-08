@extends('layouts.app')
@section('content')
<x-auth-card :title="__('Reset password')" :subtitle="__('Enter your email and we will send you a reset link.')">
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-field name="email" type="email" :label="__('Email')" required autocomplete="email"/>
        <button class="btn-primary w-full py-3">{{ __('Send reset link') }}</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="text-brand-2 hover:underline">{{ __('Back to log in') }}</a></p>
</x-auth-card>
@endsection
