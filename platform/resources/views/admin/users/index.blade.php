@extends('layouts.admin')
@section('title', 'Users')

@section('content')
<form method="get" class="mb-4 flex flex-wrap gap-2">
    <input type="search" name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Nickname or email">
    <select name="role" class="input w-auto"><option value="">Any role</option>@foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}" @selected(request('role') === $r->value)>{{ $r->label() }}</option>@endforeach</select>
    <select name="state" class="input w-auto"><option value="">Everyone</option><option value="staff" @selected(request('state') === 'staff')>Staff</option><option value="suspended" @selected(request('state') === 'suspended')>Suspended</option></select>
    <button class="btn-ghost">Filter</button>
</form>
<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>User</th><th>Role</th><th>Level</th><th>Verified</th><th>Last active</th><th>Joined</th><th>State</th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            <tr>
                <td><a href="{{ route('admin.users.show', $u) }}" class="font-medium hover:text-brand-2">{{ $u->nickname }}</a><div class="text-xs text-ink-3">{{ $u->email }}</div></td>
                <td class="text-xs">{{ $u->role->label() }}</td>
                <td class="tabular-nums">{{ $u->level }} <span class="text-xs text-ink-3">({{ number_format($u->xp) }} XP)</span></td>
                <td>{!! $u->email_verified_at ? '<span class="text-ok">✓</span>' : '<span class="text-ink-3">—</span>' !!}</td>
                <td class="text-xs text-ink-3">{{ $u->last_active_at?->diffForHumans() ?? '—' }}</td>
                <td class="text-xs text-ink-3">{{ $u->created_at->toDateString() }}</td>
                <td>@if ($u->suspended_at)<x-admin.status value="suspended" class="!bg-bad/15 !text-bad"/>@else<span class="text-xs text-ink-3">active</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-6 text-center text-ink-3">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
