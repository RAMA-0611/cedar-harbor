<?php

namespace Tests\Feature\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageRouteTest extends TestCase
{
    public function test_public_file_is_served_without_storage_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs('products', UploadedFile::fake()->image('a.jpg'), 'a.jpg');

        $this->get('/storage/products/a.jpg')->assertOk();
    }

    public function test_missing_file_returns_404(): void
    {
        Storage::fake('public');

        $this->get('/storage/products/nope.jpg')->assertNotFound();
    }

    public function test_path_traversal_is_rejected(): void
    {
        Storage::fake('public');

        $this->get('/storage/..%2F..%2F.env')->assertNotFound();
        $this->get('/storage/products/../../../.env')->assertNotFound();
    }
}
