<?php

use App\Services\Import\Adapters\CsvAdapter;
use App\Services\Import\Adapters\GameDistributionAdapter;
use App\Services\Import\Adapters\JsonAdapter;
use App\Services\Import\Adapters\ManualAdapter;

return [
    /*
    | Provisional brand name. Trademark/domain availability has NOT been checked;
    | see docs/PROGRESS.md before launch.
    */
    'brand' => env('PLATFORM_BRAND', 'Nebulo'),
    'tagline' => 'Play instantly. No downloads.',

    // Shown on legal pages via the :operator / :contact_email / :jurisdiction tokens.
    'legal' => [
        'operator' => env('LEGAL_OPERATOR', 'the operator of this website'),
        'contact_email' => env('CONTACT_EMAIL', 'support@example.com'),
        'jurisdiction' => env('LEGAL_JURISDICTION', 'the country where the operator is established'),
    ],

    'locales' => [
        'en' => ['name' => 'English', 'native' => 'English', 'hreflang' => 'en'],
        'ka' => ['name' => 'Georgian', 'native' => 'ქართული', 'hreflang' => 'ka'],
        'tr' => ['name' => 'Turkish', 'native' => 'Türkçe', 'hreflang' => 'tr'],
        'ru' => ['name' => 'Russian', 'native' => 'Русский', 'hreflang' => 'ru'],
    ],
    'default_locale' => 'en',

    /*
    | Games run inside sandboxed iframes. In production, GAMES_ORIGIN should be a
    | separate registrable domain (e.g. https://play.example-games.net) serving
    | only static game files, so game code never shares the app's origin.
    | When empty, the app origin is used and isolation relies on the iframe
    | sandbox (no allow-same-origin), which gives game documents an opaque origin.
    */
    'games_origin' => rtrim((string) env('GAMES_ORIGIN', ''), '/'),

    // Serve "Disallow: /" in robots.txt even in production (e.g. a staging server).
    'block_indexing' => (bool) env('BLOCK_INDEXING', false),

    'iframe_sandbox' => 'allow-scripts allow-pointer-lock allow-popups allow-popups-to-escape-sandbox allow-forms',
    'iframe_allow' => 'fullscreen; gamepad; autoplay; accelerometer; gyroscope',

    'uploads' => [
        'max_game_package_kb' => (int) env('MAX_GAME_PACKAGE_KB', 200 * 1024),
        'max_thumbnail_kb' => 2048,
        'max_extracted_files' => 5000,
        'max_extracted_bytes' => 600 * 1024 * 1024,
        // Only static asset types may be extracted from game packages.
        'allowed_package_extensions' => [
            'html', 'htm', 'js', 'mjs', 'css', 'json', 'txt', 'xml', 'map',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp',
            'mp3', 'ogg', 'oga', 'wav', 'm4a', 'aac', 'mp4', 'webm',
            'woff', 'woff2', 'ttf', 'otf', 'eot', 'fnt',
            'wasm', 'data', 'unityweb', 'br', 'gz', 'bin', 'swf', 'atlas', 'glb', 'gltf', 'csv', 'tmx', 'tsx',
        ],
    ],

    'scores' => [
        // Max plausible points per second per game key, for casual plausibility checks.
        'max_points_per_second_default' => 1000,
    ],

    'privacy' => [
        // Rotating salt makes visitor hashes unlinkable across days.
        'visitor_hash_salt' => env('VISITOR_HASH_SALT', env('APP_KEY')),
    ],

    'analytics' => [
        'ga4_measurement_id' => env('GA4_MEASUREMENT_ID'),
        'search_console_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

    'ads' => [
        'adsense_client' => env('ADSENSE_CLIENT'), // only set after AdSense approval
    ],

    'import' => [
        'adapters' => [
            'manual' => ManualAdapter::class,
            'csv' => CsvAdapter::class,
            'json' => JsonAdapter::class,
            'gamedistribution' => GameDistributionAdapter::class,
        ],
    ],

    'per_page' => 36,
];
