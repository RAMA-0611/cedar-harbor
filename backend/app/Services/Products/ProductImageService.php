<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Exceptions\ApiException;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ProductImageService
{
    private const MAX_BYTES = 5_242_880; // 5 MB

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var array<string, string> */
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(Product $product, UploadedFile $file): Product
    {
        $this->assertValidUpload($file);

        $mime = (string) $file->getMimeType();
        $ext = self::MIME_EXT[$mime] ?? null;
        if ($ext === null) {
            throw new BadRequestHttpException('Unsupported image MIME type.');
        }

        $relative = 'products/'.Str::uuid()->toString().'.'.$ext;
        $stored = Storage::disk('public')->putFileAs(
            'products',
            $file,
            basename($relative),
            ['visibility' => 'public'],
        );

        if ($stored === false) {
            throw ApiException::unprocessable(
                'IMAGE_STORE_FAILED',
                'Could not store product image.',
            );
        }

        $previous = $product->image_path;
        $product->image_path = $relative;
        $product->save();

        if (is_string($previous) && $previous !== '' && $previous !== $relative) {
            $this->deleteFileIfSafe($previous);
        }

        return $product->refresh();
    }

    public function clear(Product $product): Product
    {
        $previous = $product->image_path;
        $product->image_path = null;
        $product->save();

        if (is_string($previous) && $previous !== '') {
            $this->deleteFileIfSafe($previous);
        }

        return $product->refresh();
    }

    public static function publicUrl(?string $imagePath): ?string
    {
        if ($imagePath === null || $imagePath === '') {
            return null;
        }

        return Storage::disk('public')->url($imagePath);
    }

    private function assertValidUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new BadRequestHttpException('Invalid image upload.');
        }

        if ($file->getSize() === false || $file->getSize() > self::MAX_BYTES) {
            throw ApiException::unprocessable(
                'IMAGE_TOO_LARGE',
                'Image must be at most 5 MB.',
            );
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            throw ApiException::unprocessable(
                'IMAGE_MIME_INVALID',
                'Image must be jpeg, png, or webp.',
                ['mime' => $mime],
            );
        }

        // Reject path-like original names for defense in depth (we never use them).
        $original = (string) $file->getClientOriginalName();
        if (str_contains($original, '..') || str_contains($original, '/') || str_contains($original, '\\')) {
            throw new BadRequestHttpException('Unsafe original filename.');
        }
    }

    private function deleteFileIfSafe(string $relative): void
    {
        $normalized = ltrim(str_replace('\\', '/', $relative), '/');
        if ($normalized === '' || str_contains($normalized, '..') || ! str_starts_with($normalized, 'products/')) {
            return;
        }

        if (Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }
}
