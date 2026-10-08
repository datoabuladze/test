<?php

namespace App\Support;

/** Helpers for name[en], name[ka], ... form input. */
class Translatable
{
    /** Validation rules for a translatable field. */
    public static function rules(string $field, bool $required = false, int $max = 255): array
    {
        $rules = [$field => ['nullable', 'array'], $field.'.en' => [$required ? 'required' : 'nullable', 'string', 'max:'.$max]];
        foreach (array_keys(config('platform.locales')) as $code) {
            if ($code !== 'en') {
                $rules[$field.'.'.$code] = ['nullable', 'string', 'max:'.$max];
            }
        }

        return $rules;
    }

    /** Drops empty and unknown locales; returns null when nothing is left. */
    public static function clean(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        $out = [];
        foreach (array_keys(config('platform.locales')) as $code) {
            $v = trim((string) ($value[$code] ?? ''));
            if ($v !== '') {
                $out[$code] = $v;
            }
        }

        return $out ?: null;
    }
}
