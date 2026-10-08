{{-- Sort + quick filters for listing pages. Expects $sort. --}}
@php
    $sorts = ['popular' => __('Most played'), 'trending' => __('Trending'), 'new' => __('Newest'), 'rating' => __('Top rated'), 'az' => __('A–Z')];
    $q = request()->query();
@endphp
<div class="flex flex-wrap items-center gap-2">
    <x-icon name="filter" class="size-4 text-ink-3"/>
    @foreach ($sorts as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'page' => null]) }}" class="chip {{ $sort === $key ? 'chip-active' : '' }}" rel="nofollow">{{ $label }}</a>
    @endforeach
    <span class="mx-1 h-5 w-px bg-line"></span>
    <a href="{{ request()->fullUrlWithQuery(['device' => ($q['device'] ?? null) === 'mobile' ? null : 'mobile', 'page' => null]) }}" rel="nofollow"
       class="chip {{ ($q['device'] ?? null) === 'mobile' ? 'chip-active' : '' }}"><x-icon name="device" class="size-3.5"/>{{ __('Mobile') }}</a>
    <a href="{{ request()->fullUrlWithQuery(['multiplayer' => ! empty($q['multiplayer']) ? null : 1, 'page' => null]) }}" rel="nofollow"
       class="chip {{ ! empty($q['multiplayer']) ? 'chip-active' : '' }}"><x-icon name="users" class="size-3.5"/>{{ __('Multiplayer') }}</a>
</div>
