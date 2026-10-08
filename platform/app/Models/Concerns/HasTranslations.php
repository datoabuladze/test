<?php

namespace App\Models\Concerns;

/**
 * Translatable attributes stored as JSON objects keyed by locale.
 *
 * Models list their translatable columns in `protected array $translatable`.
 * Reading `$model->tr('title')` returns the value for the current locale,
 * falling back to the default locale and then to the first non-empty value.
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        foreach ($this->translatable as $attribute) {
            $this->casts[$attribute] = 'array';
        }
    }

    public function tr(string $attribute, ?string $locale = null): string
    {
        $values = $this->getAttribute($attribute);
        if (! is_array($values)) {
            return (string) ($values ?? '');
        }
        $locale ??= app()->getLocale();
        $fallback = config('platform.default_locale', 'en');

        foreach ([$locale, $fallback] as $candidate) {
            if (isset($values[$candidate]) && $values[$candidate] !== '') {
                return (string) $values[$candidate];
            }
        }
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    public function hasTranslation(string $attribute, string $locale): bool
    {
        $values = $this->getAttribute($attribute);

        return is_array($values) && ! empty($values[$locale]);
    }

    public function setTranslation(string $attribute, string $locale, ?string $value): static
    {
        $values = $this->getAttribute($attribute);
        $values = is_array($values) ? $values : [];
        if ($value === null || $value === '') {
            unset($values[$locale]);
        } else {
            $values[$locale] = $value;
        }
        $this->setAttribute($attribute, $values);

        return $this;
    }

    /** @return list<string> */
    public function getTranslatableAttributes(): array
    {
        return $this->translatable;
    }
}
