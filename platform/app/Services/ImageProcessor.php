<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Decodes uploaded images and re-encodes them to WebP with GD. Serving only
 * re-encoded output strips metadata and defeats polyglot/script-in-image uploads.
 */
class ImageProcessor
{
    public function storeSquare(UploadedFile $file, string $dir, int $size): string
    {
        $src = $this->load($file);
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);

        return $this->save($dst, $dir);
    }

    /** Cover-crop to the given box (default 4:3 game thumbnail). */
    public function storeCover(UploadedFile $file, string $dir, int $width = 640, int $height = 480): string
    {
        $src = $this->load($file);
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = max($width / $w, $height / $h);
        $cropW = (int) round($width / $scale);
        $cropH = (int) round($height / $scale);
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $cropW) / 2), (int) (($h - $cropH) / 2), $width, $height, $cropW, $cropH);

        return $this->save($dst, $dir);
    }

    private function load(UploadedFile $file): \GdImage
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[0] < 16 || $info[1] < 16 || $info[0] * $info[1] > 40_000_000) {
            throw ValidationException::withMessages(['image' => __('The image could not be read.')]);
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
            IMAGETYPE_GIF => @imagecreatefromgif($file->getRealPath()),
            default => false,
        };
        if (! $img) {
            throw ValidationException::withMessages(['image' => __('Unsupported image format.')]);
        }
        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }

        return $img;
    }

    private function save(\GdImage $img, string $dir): string
    {
        $path = trim($dir, '/').'/'.Str::random(32).'.webp';
        ob_start();
        imagewebp($img, null, 82);
        $bytes = (string) ob_get_clean();
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }
}
