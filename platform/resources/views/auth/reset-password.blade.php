@extends('layouts.app')
@section('content')
<x-auth-card :title="__('Choose a new password')">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" type="email" :label="__('Email')" :value="$email" required autocomplete="email"/>
        <x-field name="password" type="password" :label="__('New password')" required autocomplete="new-password"/>
        <x-field name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password"/>
        <button class="btn-primary w-full py-3">{{ __('Reset password') }}</button>
    </form>
</x-auth-card>
@endsection
