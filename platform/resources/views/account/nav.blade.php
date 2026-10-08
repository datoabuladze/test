@php $items = [
    ['account.dashboard', 'user', __('Overview')], ['account.favorites', 'heart', __('Favorites')],
    ['account.history', 'history', __('History')], ['account.achievements', 'medal', __('Achievements')],
    ['account.settings', 'settings', __('Settings')],
]; @endphp
<nav class="rail mb-6 border-b border-line pb-0" aria-label="{{ __('Account') }}">
    @foreach ($items as [$route, $icon, $label])
        <a href="{{ route($route) }}" class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium {{ request()->routeIs($route) ? 'border-brand text-ink' : 'border-transparent text-ink-3 hover:text-ink' }}">
            <x-icon :name="$icon" class="size-4"/>{{ $label }}
        </a>
    @endforeach
</nav>
