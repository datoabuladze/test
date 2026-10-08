<?php

namespace App\Support;

use App\Http\Controllers\Admin\TranslationController;
use Illuminate\Translation\FileLoader;

/**
 * Loads lang/{locale}.json, then applies the admin's overrides from storage on top.
 * An empty override removes the string, so the English key is shown.
 */
class OverridableFileLoader extends FileLoader
{
    protected function loadJsonPaths($locale)
    {
        $lines = parent::loadJsonPaths($locale);
        $path = TranslationController::overridesPath("$locale.json");
        if ($this->files->exists($path)) {
            $decoded = json_decode($this->files->get($path), true);
            if (is_array($decoded)) {
                $lines = array_merge($lines, $decoded);
            }
        }

        return array_filter($lines, fn ($v) => $v !== '');
    }
}
