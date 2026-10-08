<?php

namespace App\Enums;

enum GameEngine: string
{
    case Html5 = 'html5';       // self-hosted HTML5/Canvas/JS bundle with an index.html entry
    case Phaser = 'phaser';     // self-hosted Phaser game bundle
    case Unity = 'unity';       // self-hosted Unity WebGL build
    case Ruffle = 'ruffle';     // authorized SWF played through self-hosted Ruffle
    case Iframe = 'iframe';     // authorized third-party embed URL
    case Original = 'original'; // first-party game shipped in public/games/originals

    public function label(): string
    {
        return match ($this) {
            self::Html5 => 'HTML5',
            self::Phaser => 'Phaser',
            self::Unity => 'Unity WebGL',
            self::Ruffle => 'Flash (Ruffle)',
            self::Iframe => 'Embedded',
            self::Original => 'Original',
        };
    }

    public function isSelfHosted(): bool
    {
        return $this !== self::Iframe;
    }
}
