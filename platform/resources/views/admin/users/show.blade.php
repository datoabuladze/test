@extends('layouts.admin')
@section('title', 'User · '.$user->nickname)

@php $me = auth()->user(); $self = $me->is($user); @endphp
@section('content')
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="space-y-6">
        <section class="card p-5">
            <div class="flex items-center gap-4">
                <div class="flex size-14 items-center justify-center overflow-hidden rounded-full bg-brand/20 text-lg font-bold">
                    @if ($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="" class="size-full object-cover">@else{{ $user->initials() }}@endif
                </div>
                <div>
                    <h2 class="text-xl font-bold">{{ $user->nickname }}</h2>
                    <p class="text-sm text-ink-3">{{ $user->email }} · {{ $user->role->label() }} · joined {{ $user->created_at->toFormattedDateString() }}</p>
                    @if ($user->suspended_at)<p class="mt-1 text-sm text-bad">Suspended {{ $user->suspended_at->diffForHumans() }}: {{ $user->suspension_reason }}</p>@endif
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <x-admin.stat label="Level" :value="$user->level" icon="medal"/>
                <x-admin.stat label="Plays" :value="$user->plays_count" icon="play"/>
                <x-admin.stat label="Favorites" :value="$user->favorites_count" icon="heart"/>
                <x-admin.stat label="Ratings" :value="$user->ratings_count" icon="star"/>
                <x-admin.stat label="Scores" :value="$user->scores_count" icon="trophy"/>
            </div>
        </section>
        <section class="card p-4">
            <h2 class="mb-2 font-bold">Recent plays</h2>
            <ul class="divide-y divide-line text-sm">
                @forelse ($recentPlays as $p)<li class="flex justify-between py-1.5"><span>{{ $p->game?->tr('title', 'en') ?? 'deleted' }}</span><span class="text-ink-3">{{ $p->created_at?->diffForHumans() }} · {{ $p->load_status }}</span></li>@empty<li class="text-ink-3">No plays.</li>@endforelse
            </ul>
        </section>
        <section class="card p-4">
            <h2 class="mb-2 font-bold">Moderation history</h2>
            <ul class="divide-y divide-line text-sm">
                @forelse ($audit as $a)<li class="py-1.5"><code class="text-xs">{{ $a->action }}</code> by {{ $a->user?->nickname ?? 'system' }} · <span class="text-ink-3">{{ $a->created_at?->diffForHumans() }}</span> @if ($a->meta)<span class="text-xs text-ink-3">{{ json_encode($a->meta, JSON_UNESCAPED_UNICODE) }}</span>@endif</li>@empty<li class="text-ink-3">No moderation actions.</li>@endforelse
            </ul>
        </section>
    </div>
    <aside class="space-y-4">
        @if ($self)
            <p class="card p-4 text-sm text-ink-3">This is your account. Use Account settings on the site to change it.</p>
        @else
            <section class="card space-y-3 p-4">
                <h2 class="font-bold">Suspension</h2>
                @if ($user->suspended_at)
                    <form method="post" action="{{ route('admin.users.unsuspend', $user) }}">@csrf<button class="btn-ghost w-full">Lift suspension</button></form>
                @else
                    <form method="post" action="{{ route('admin.users.suspend', $user) }}" class="space-y-2" x-data="confirmForm" data-confirm="Suspend {{ $user->nickname }}?" @submit="confirmSubmit">
                        @csrf
                        <textarea name="reason" rows="2" class="input" placeholder="Reason (kept in the audit log)" required></textarea>
                        <button class="btn-danger w-full">Suspend user</button>
                    </form>
                @endif
            </section>
            @if ($me->hasPermission('users.manage'))
                <section class="card space-y-3 p-4">
                    <h2 class="font-bold">Role</h2>
                    <form method="post" action="{{ route('admin.users.role', $user) }}" class="space-y-2">
                        @csrf
                        <select name="role" class="input">
                            @foreach (\App\Enums\Role::cases() as $r)
                                @if ($r !== \App\Enums\Role::SuperAdmin || $me->role === \App\Enums\Role::SuperAdmin)
                                    <option value="{{ $r->value }}" @selected($user->role === $r)>{{ $r->label() }}</option>
                                @endif
                            @endforeach
                        </select>
                        <p class="help">Permissions: {{ implode(', ', $user->role->permissions()) ?: 'none' }}</p>
                        <button class="btn-ghost w-full">Change role</button>
                    </form>
                </section>
            @endif
        @endif
    </aside>
</div>
@endsection
