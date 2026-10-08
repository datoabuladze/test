<?php

namespace App\Services;

use App\Enums\GameEngine;
use App\Models\Game;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Safely installs an uploaded game package (ZIP of static files, or a single SWF).
 *
 * Defences: extension allow-list (no server-side code is ever written), path traversal
 * and absolute-path rejection, symlink rejection, entry-count and uncompressed-size
 * limits (zip bombs), and extraction into a fresh random directory that the web server
 * serves as static content only.
 */
class GamePackageInstaller
{
    public function install(Game $game, UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return $ext === 'swf' ? $this->installSwf($game, $file) : $this->installZip($game, $file);
    }

    private function targetDir(Game $game): array
    {
        $rel = 'game-files/'.$game->id.'-'.Str::lower(Str::random(12));
        $abs = public_path($rel);
        File::ensureDirectoryExists($abs, 0755);

        return [$rel, $abs];
    }

    private function installSwf(Game $game, UploadedFile $file): array
    {
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 3);
        if (! in_array($head, ['FWS', 'CWS', 'ZWS'], true)) {
            throw ValidationException::withMessages(['package' => 'This file is not a valid SWF (bad signature).']);
        }
        $version = ord((string) file_get_contents($file->getRealPath(), false, null, 3, 1));
        [$rel, $abs] = $this->targetDir($game);
        $file->move($abs, 'game.swf');

        return [
            'engine' => GameEngine::Ruffle,
            'entry_path' => "$rel/game.swf",
            'engine_config' => ['swf_version' => $version, 'compression' => $head],
            'files' => 1,
        ];
    }

    private function installZip(Game $game, UploadedFile $file): array
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath(), ZipArchive::RDONLY) !== true) {
            throw ValidationException::withMessages(['package' => 'The ZIP file could not be opened.']);
        }

        $maxFiles = (int) config('platform.uploads.max_extracted_files');
        $maxBytes = (int) config('platform.uploads.max_extracted_bytes');
        $allowed = config('platform.uploads.allowed_package_extensions');
        if ($zip->numFiles > $maxFiles) {
            throw ValidationException::withMessages(['package' => "The package contains more than $maxFiles files."]);
        }

        $entries = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) $stat['name'];
            if (str_ends_with($name, '/')) {
                continue; // directory entry
            }
            $this->assertSafeName($name);
            // Unix symlinks are stored with S_IFLNK in the upper 16 bits of the external attributes.
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                throw ValidationException::withMessages(['package' => "Symbolic links are not allowed ($name)."]);
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (str_starts_with(basename($name), '.') || ! in_array($ext, $allowed, true)) {
                if (in_array(basename($name), ['.DS_Store', 'Thumbs.db'], true) || str_starts_with($name, '__MACOSX/')) {
                    continue;
                }
                throw ValidationException::withMessages(['package' => "File type not allowed in game packages: $name"]);
            }
            $total += (int) $stat['size'];
            if ($total > $maxBytes) {
                throw ValidationException::withMessages(['package' => 'The package is too large when extracted.']);
            }
            $entries[$i] = $name;
        }

        $index = $this->findEntry(array_values($entries));
        if (! $index) {
            throw ValidationException::withMessages(['package' => 'No index.html found at the top level of the package.']);
        }

        [$rel, $abs] = $this->targetDir($game);
        $written = 0;
        foreach ($entries as $i => $name) {
            $dest = $abs.'/'.$name;
            File::ensureDirectoryExists(dirname($dest), 0755);
            $in = $zip->getStream($name);
            if (! $in) {
                continue;
            }
            $out = fopen($dest, 'wb');
            $bytes = 0;
            while (! feof($in)) {
                $chunk = fread($in, 1 << 16);
                $bytes += strlen((string) $chunk);
                if ($bytes > $maxBytes) { // guards against lying size headers
                    fclose($out);
                    fclose($in);
                    File::deleteDirectory($abs);
                    throw ValidationException::withMessages(['package' => 'The package is too large when extracted.']);
                }
                fwrite($out, (string) $chunk);
            }
            fclose($out);
            fclose($in);
            $written++;
        }
        $zip->close();

        $result = ['engine' => null, 'entry_path' => "$rel/$index", 'engine_config' => null, 'files' => $written];
        if ($unity = $this->detectUnity($abs, dirname($index))) {
            $result['engine'] = GameEngine::Unity;
            $result['engine_config'] = $unity;
        } elseif ($this->mentions($abs.'/'.$index, 'phaser')) {
            $result['engine'] = GameEngine::Phaser;
        } else {
            $result['engine'] = GameEngine::Html5;
        }

        return $result;
    }

    private function assertSafeName(string $name): void
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/')
            || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('/^[a-zA-Z]:/', $name) || strlen($name) > 400) {
            throw ValidationException::withMessages(['package' => "Unsafe path in package: $name"]);
        }
    }

    /** index.html at the root, or inside a single top-level folder. */
    private function findEntry(array $names): ?string
    {
        if (in_array('index.html', $names, true)) {
            return 'index.html';
        }
        $roots = array_unique(array_map(fn ($n) => explode('/', $n)[0], $names));
        if (count($roots) === 1 && in_array($roots[0].'/index.html', $names, true)) {
            return $roots[0].'/index.html';
        }

        return null;
    }

    private function detectUnity(string $abs, string $entryDir): ?array
    {
        $base = rtrim($abs.'/'.($entryDir === '.' ? '' : $entryDir), '/');
        $loaders = glob($base.'/Build/*.loader.js') ?: [];
        if (! $loaders) {
            return null;
        }
        $name = basename($loaders[0], '.loader.js');
        $find = function (array $suffixes) use ($base, $name) {
            foreach ($suffixes as $s) {
                if (is_file("$base/Build/$name$s")) {
                    return "Build/$name$s";
                }
            }

            return null;
        };

        return array_filter([
            'loader' => "Build/$name.loader.js",
            'data' => $find(['.data', '.data.br', '.data.gz', '.data.unityweb']),
            'framework' => $find(['.framework.js', '.framework.js.br', '.framework.js.gz', '.framework.js.unityweb']),
            'code' => $find(['.wasm', '.wasm.br', '.wasm.gz', '.wasm.unityweb']),
            'product' => $name,
        ]);
    }

    private function mentions(string $file, string $needle): bool
    {
        return is_file($file) && stripos((string) file_get_contents($file, false, null, 0, 200000), $needle) !== false;
    }
}
