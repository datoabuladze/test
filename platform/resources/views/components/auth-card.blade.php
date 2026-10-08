@props(['title', 'subtitle' => null])
<div class="container-page flex min-h-[calc(100dvh-12rem)] items-center justify-center py-10">
    <div class="relative w-full max-w-md">
        <div class="absolute -inset-10 -z-10 rounded-full bg-brand/15 blur-3xl"></div>
        <div class="card p-6 shadow-2xl sm:p-8">
            <h1 class="text-2xl font-black">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-1 text-sm text-ink-2">{{ $subtitle }}</p>@endif
            @if (session('status'))
                <div class="mt-4 rounded-lg border border-ok/30 bg-ok/10 px-3 py-2 text-sm text-ok" role="status">{{ session('status') }}</div>
            @endif
            <div class="mt-6">{{ $slot }}</div>
        </div>
    </div>
</div>
