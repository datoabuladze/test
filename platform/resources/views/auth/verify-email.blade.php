@extends('layouts.app')
@section('content')
<x-auth-card :title="__('Check your inbox')" :subtitle="__('We sent a confirmation link to :email. You can keep playing while you wait.', ['email' => auth()->user()->email])">
    <form method="post" action="{{ route('verification.send') }}" class="flex flex-wrap gap-3">
        @csrf
        <button class="btn-primary">{{ __('Resend link') }}</button>
        <a href="{{ route('home') }}" class="btn-ghost">{{ __('Start playing') }}</a>
    </form>
</x-auth-card>
@endsection
