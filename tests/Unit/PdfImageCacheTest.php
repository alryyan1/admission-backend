<?php

namespace Tests\Unit;

use App\Support\Pdf\PdfImageCache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfImageCacheTest extends TestCase
{
    private function writePng(string $relativePath, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorallocatealpha($image, $x % 256, 80, 160, $x % 2 === 0 ? 0 : 60);
            imageline($image, $x, 0, $x, $height - 1, $color);
        }

        ob_start();
        imagepng($image, null, 9);
        Storage::disk('public')->put($relativePath, ob_get_clean());
        imagedestroy($image);

        return Storage::disk('public')->path($relativePath);
    }

    public function test_images_within_the_limit_are_used_as_is(): void
    {
        Storage::fake('public');
        $path = $this->writePng('facility/small.png', 300, 200);

        $this->assertSame($path, app(PdfImageCache::class)->resolve($path));
        $this->assertFalse(Storage::disk('public')->exists('pdf-cache'));
    }

    public function test_oversized_images_are_downscaled_to_a_cached_copy_keeping_aspect_ratio(): void
    {
        Storage::fake('public');
        $path = $this->writePng('facility/large.png', 1254, 627);

        $resolved = app(PdfImageCache::class)->resolve($path);

        $this->assertNotSame($path, $resolved);
        $this->assertFileExists($resolved);
        [$width, $height] = getimagesize($resolved);
        $this->assertSame(600, $width);
        $this->assertSame(300, $height);
        $this->assertLessThan(filesize($path), filesize($resolved));
    }

    public function test_the_cached_copy_is_flattened_to_rgb_without_an_alpha_channel(): void
    {
        Storage::fake('public');
        $path = $this->writePng('facility/transparent.png', 1000, 800);

        $resolved = app(PdfImageCache::class)->resolve($path);

        $contents = file_get_contents($resolved);
        $colorType = ord($contents[strpos($contents, 'IHDR') + 13]);
        $this->assertSame(2, $colorType, 'Expected RGB (color type 2); alpha channels slow TCPDF down.');
    }

    public function test_the_cached_copy_is_reused_on_subsequent_calls(): void
    {
        Storage::fake('public');
        $path = $this->writePng('facility/reused.png', 1000, 1000);

        $first = app(PdfImageCache::class)->resolve($path);
        $second = app(PdfImageCache::class)->resolve($path);

        $this->assertSame($first, $second);
        $this->assertCount(1, Storage::disk('public')->files('pdf-cache'));
    }

    public function test_a_replaced_source_image_produces_a_new_cached_copy(): void
    {
        Storage::fake('public');
        $path = $this->writePng('facility/replaced.png', 1000, 1000);
        $first = app(PdfImageCache::class)->resolve($path);

        $this->writePng('facility/replaced.png', 900, 1200);
        $second = app(PdfImageCache::class)->resolve($path);

        $this->assertNotSame($first, $second);
    }

    public function test_unreadable_images_fall_back_to_the_original_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('facility/broken.png', 'not an image');
        $path = Storage::disk('public')->path('facility/broken.png');

        $this->assertSame($path, app(PdfImageCache::class)->resolve($path));
    }
}
