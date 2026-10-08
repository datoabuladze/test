@props(['games'])
<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 xs:grid-cols-2 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 3xl:grid-cols-8']) }}>
    @foreach ($games as $game)
        <x-game-card :game="$game" size="grid" />
    @endforeach
</div>
