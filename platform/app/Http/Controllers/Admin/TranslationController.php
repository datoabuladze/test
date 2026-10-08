<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/**
 * Edits UI strings in lang/{locale}.json. Keys are the English source strings
 * used with __('...'); English itself is the key, so only other locales are edited.
 */
class TranslationController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $locales = $this->editableLocales();
        $this->ensureFiles($locales);
        $files = $this->readAll();
        $keys = $this->allKeys($files);

        $q = trim((string) $request->query('q', ''));
        $onlyMissing = $request->boolean('missing');

        $filtered = array_values(array_filter($keys, function (string $key) use ($q, $onlyMissing, $files, $locales) {
            if ($onlyMissing && ! collect($locales)->contains(fn ($l) => ($files[$l][$key] ?? '') === '')) {
                return false;
            }
            if ($q === '') {
                return true;
            }
            if (mb_stripos($key, $q) !== false) {
                return true;
            }
            foreach ($files as $strings) {
                if (isset($strings[$key]) && mb_stripos((string) $strings[$key], $q) !== false) {
                    return true;
                }
            }

            return false;
        }));

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            array_slice($filtered, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($filtered), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $missingCounts = collect($locales)->mapWithKeys(fn ($l) => [
            $l => count(array_filter($keys, fn ($k) => ($files[$l][$k] ?? '') === '')),
        ])->all();

        return view('admin.translations.index', [
            'paginator' => $paginator,
            'files' => $files,
            'locales' => $locales,
            'localeMeta' => config('platform.locales'),
            'total' => count($keys),
            'missingCounts' => $missingCounts,
            'q' => $q,
            'onlyMissing' => $onlyMissing,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $locales = $this->editableLocales();
        $data = $request->validate([
            'translations' => ['required', 'array'],
            'translations.*' => ['array'],
            'translations.*.*' => ['nullable', 'string', 'max:5000'],
        ]);
        $unknown = array_diff(array_keys($data['translations']), $locales);
        if ($unknown) {
            return back()->withErrors(['translations' => 'Unsupported locale: '.implode(', ', $unknown).'.']);
        }

        $this->ensureFiles($locales);
        $files = $this->readAll();
        $known = array_flip($this->allKeys($files));
        $changed = [];

        foreach ($data['translations'] as $locale => $values) {
            $strings = $files[$locale] ?? [];
            $before = $strings;
            foreach ($values as $field => $value) {
                $key = $this->decodeKey((string) $field);
                if (! isset($known[$key])) {
                    continue; // only existing keys can be edited
                }
                $value = trim((string) $value);
                if ($value === '') {
                    unset($strings[$key]);
                } else {
                    $strings[$key] = $value;
                }
            }
            if ($strings !== $before) {
                $this->write($locale, $strings);
                $changed[$locale] = count(array_diff_assoc($strings, $before)) + count(array_diff_key($before, $strings));
            }
        }

        Audit::log('translations.update', null, ['changed' => $changed]);

        return back()->with('status', $changed ? 'Translations saved ('.collect($changed)->map(fn ($n, $l) => "$l: $n")->implode(', ').').' : 'No changes.');
    }

    /** Form field name for a key; keys with brackets are encoded so PHP's input parser keeps them intact. */
    public static function encodeKey(string $key): string
    {
        return preg_match('/[\[\]]/', $key) ? '__b64_'.rtrim(strtr(base64_encode($key), '+/', '-_'), '=') : $key;
    }

    private function decodeKey(string $field): string
    {
        if (str_starts_with($field, '__b64_')) {
            return (string) base64_decode(strtr(substr($field, 6), '-_', '+/'), true);
        }

        return $field;
    }

    /** @return list<string> */
    private function editableLocales(): array
    {
        return array_values(array_filter(array_keys(config('platform.locales')), fn ($l) => $l !== 'en'));
    }

    private function ensureFiles(array $locales): void
    {
        File::ensureDirectoryExists(lang_path());
        foreach ($locales as $locale) {
            $path = lang_path("$locale.json");
            if (! File::exists($path)) {
                File::put($path, "{}\n");
            }
        }
    }

    /** @return array<string, array<string, string>> locale => strings, for every lang/*.json file */
    private function readAll(): array
    {
        $out = [];
        foreach (File::glob(lang_path('*.json')) as $path) {
            $decoded = json_decode((string) File::get($path), true);
            $out[pathinfo($path, PATHINFO_FILENAME)] = is_array($decoded) ? array_map('strval', array_filter($decoded, 'is_scalar')) : [];
        }

        return $out;
    }

    /** @return list<string> */
    private function allKeys(array $files): array
    {
        $keys = [];
        foreach ($files as $strings) {
            foreach (array_keys($strings) as $k) {
                $keys[(string) $k] = true;
            }
        }
        $keys = array_keys($keys);
        sort($keys, SORT_STRING | SORT_FLAG_CASE);

        return array_map('strval', $keys);
    }

    private function write(string $locale, array $strings): void
    {
        ksort($strings, SORT_STRING);
        $json = json_encode($strings ?: new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        File::put(lang_path("$locale.json"), $json."\n", true);
    }
}
