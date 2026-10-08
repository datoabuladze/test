@props(['title', 'icon' => 'gamepad'])
<div {{ $attributes->merge(['class' => 'card flex flex-col items-center px-6 py-14 text-center']) }}>
    <div class="relative mb-5">
        <div class="absolute inset-0 rounded-full bg-brand/30 blur-2xl"></div>
        <div class="relative flex size-16 items-center justify-center rounded-2xl border border-line bg-card-2 text-brand animate-float">
            <x-icon :name="$icon" class="size-8"/>
        </div>
    </div>
    <h2 class="text-lg font-bold">{{ $title }}</h2>
    <div class="mt-2 max-w-md text-sm text-ink-2">{{ $slot }}</div>
</div>
