<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Audit;
use App\Support\Translatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const SOCIAL = [
        'twitter' => ['X / Twitter', ['twitter.com', 'x.com']],
        'discord' => ['Discord', ['discord.gg', 'discord.com']],
        'youtube' => ['YouTube', ['youtube.com', 'youtu.be']],
    ];

    public function index(): View
    {
        return view('admin.settings.index', [
            'announcement' => (array) Setting::get('site.announcement', []),
            'registrationOpen' => (bool) Setting::get('site.registration_open', true),
            'social' => (array) Setting::get('social.links', []),
            'socialNetworks' => self::SOCIAL,
            'ga4' => config('platform.analytics.ga4_measurement_id'),
            'locales' => config('platform.locales'),
            'defaultLocale' => config('platform.default_locale'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            ...Translatable::rules('announcement', max: 300),
            'registration_open' => ['nullable', 'boolean'],
        ];
        foreach (self::SOCIAL as $key => [$label, $hosts]) {
            $rules["social.$key"] = ['nullable', 'string', 'max:255', 'url:https', function ($attr, $value, $fail) use ($hosts, $label) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                $host = preg_replace('/^www\./', '', $host);
                if (! in_array($host, $hosts, true)) {
                    $fail("The $label link must point to ".implode(' or ', $hosts).'.');
                }
            }];
        }
        $data = $request->validate($rules);

        $before = [
            'registration_open' => (bool) Setting::get('site.registration_open', true),
        ];

        $announcement = Translatable::clean($request->input('announcement'));
        Setting::put('site.announcement', $announcement);
        Setting::put('site.registration_open', $request->boolean('registration_open'));
        Setting::put('social.links', array_filter(
            array_map(fn ($v) => $v ? (string) $v : null, array_intersect_key($data['social'] ?? [], self::SOCIAL))
        ));

        Audit::log('settings.update', null, [
            'registration_open' => [$before['registration_open'], $request->boolean('registration_open')],
            'announcement' => $announcement ? array_keys($announcement) : [],
        ]);

        return back()->with('status', 'Settings saved.');
    }
}
