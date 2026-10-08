<?php

namespace App\Enums;

enum HomeSectionType: string
{
    case Hero = 'hero';                 // featured carousel
    case Trending = 'trending';
    case MostPlayed = 'most_played';
    case New = 'new';
    case EditorsPicks = 'editors_picks';
    case Multiplayer = 'multiplayer';
    case MobileFriendly = 'mobile_friendly';
    case Category = 'category';         // config: {"category": "<slug>"}
    case Flash = 'flash';
    case Originals = 'originals';
    case ContinuePlaying = 'continue_playing';
    case RecentlyPlayed = 'recently_played';
    case Recommended = 'recommended';
    case Random = 'random';
    case CategoryGrid = 'category_grid';
    case SeoText = 'seo_text';          // config: {"body": {"en": "..."}}
    case Ad = 'ad';                     // config: {"placement": "home_mid"}

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
