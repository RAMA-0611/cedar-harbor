<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role' => 'ADMIN',
            'email' => 'admin-image-'.Str::lower(Str::random(6)).'@example.test',
        ]);

        $category = Category::query()->create([
            'name' => 'Img Cat',
            'slug' => 'img-cat-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'IMG-'.Str::upper(Str::random(6)),
            'name' => 'Product With Image',
            'price' => '10000.00',
            'currency' => 'COP',
            'tax_rate' => '0.1900',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_upload_replace_and_delete_image(): void
    {
        $this->actingAs($this->admin);

        $upload = $this->post(
            '/api/admin/products/'.$this->product->id.'/image',
            ['image' => UploadedFile::fake()->image('photo.jpg', 200, 200)],
            ['Accept' => 'application/json'],
        );

        $upload->assertOk()->assertJsonPath('data.id', $this->product->id);
        $this->assertNotNull($upload->json('data.image_url'));
        $this->product->refresh();
        $this->assertNotNull($this->product->image_path);
        Storage::disk('public')->assertExists($this->product->image_path);
        $firstPath = $this->product->image_path;

        $replace = $this->post(
            '/api/admin/products/'.$this->product->id.'/image',
            ['image' => UploadedFile::fake()->image('other.png', 120, 120)],
            ['Accept' => 'application/json'],
        );
        $replace->assertOk();
        $this->product->refresh();
        $this->assertNotSame($firstPath, $this->product->image_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($this->product->image_path);

        $delete = $this->deleteJson('/api/admin/products/'.$this->product->id.'/image');
        $delete->assertNoContent();
        $this->product->refresh();
        $this->assertNull($this->product->image_path);
    }

    public function test_rejects_invalid_mime_and_oversized_image(): void
    {
        $this->actingAs($this->admin);

        $invalid = $this->post(
            '/api/admin/products/'.$this->product->id.'/image',
            ['image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
            ['Accept' => 'application/json'],
        );
        $invalid->assertStatus(422);

        $big = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');
        $oversized = $this->post(
            '/api/admin/products/'.$this->product->id.'/image',
            ['image' => $big],
            ['Accept' => 'application/json'],
        );
        $oversized->assertStatus(422);
    }
}
