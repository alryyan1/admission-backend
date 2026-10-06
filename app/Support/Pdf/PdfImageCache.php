<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\Storage;

class PdfImageCache
{
    private const MAX_EDGE_PX = 600;

    private const CACHE_DIRECTORY = 'pdf-cache';

    /**
     * Bump when the derived output format changes so stale copies are ignored.
     */
    private const CACHE_VERSION = 2;

    /**
     * Return a path to a copy of the image no larger than MAX_EDGE_PX on its
     * longest edge. Oversized uploads are downscaled once and cached, so
     * TCPDF embeds a small bitmap instead of re-processing the full photo on
     * every page. The copy is flattened onto white: TCPDF handles alpha
     * channels pixel-by-pixel, which is far slower than plain RGB. Falls back
     * to the original path if anything goes wrong.
     */
    public function resolve(string $sourcePath): string
    {
        $dimensions = @getimagesize($sourcePath);

        if ($dimensions === false) {
            return $sourcePath;
        }

        [$width, $height] = $dimensions;
        $longestEdge = max($width, $height);

        if ($longestEdge <= self::MAX_EDGE_PX) {
            return $sourcePath;
        }

        $cachePath = Storage::disk('public')->path(
            self::CACHE_DIRECTORY.'/'.sha1(self::CACHE_VERSION.'|'.$sourcePath.'|'.filemtime($sourcePath).'|'.filesize($sourcePath)).'.png'
        );

        if (is_file($cachePath)) {
            return $cachePath;
        }

        return $this->downscale($sourcePath, $cachePath, $width, $height, $longestEdge);
    }

    private function downscale(string $sourcePath, string $cachePath, int $width, int $height, int $longestEdge): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($sourcePath));

        if ($source === false) {
            return $sourcePath;
        }

        $scale = self::MAX_EDGE_PX / $longestEdge;
        $targetWidth = (int) round($width * $scale);
        $targetHeight = (int) round($height * $scale);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagealphablending($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        Storage::disk('public')->makeDirectory(self::CACHE_DIRECTORY);
        $saved = imagepng($target, $cachePath, 6);

        imagedestroy($source);
        imagedestroy($target);

        return $saved ? $cachePath : $sourcePath;
    }
}
