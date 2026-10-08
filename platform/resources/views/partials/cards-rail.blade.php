<div class="rail">
    @foreach ($games as $game)
        <x-game-card :game="$game" />
    @endforeach
</div>
