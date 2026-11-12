<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_and_demo_catalog_once(): void
    {
        $this->artisan('rotor:bootstrap', [
            '--no-migrate' => true,
            '--seed-demo' => true,
            '--admin-email' => 'root@rotor.test',
            '--admin-password' => 'Clave12345',
        ])->assertSuccessful();

        $admin = User::query()->where('email', 'root@rotor.test')->firstOrFail();
        $this->assertSame('ADMIN', $admin->role);
        $products = Product::query()->count();
        $this->assertGreaterThan(0, $products);

        // Segunda ejecución: idempotente.
        $this->artisan('rotor:bootstrap', ['--no-migrate' => true, '--seed-demo' => true])->assertSuccessful();
        $this->assertSame($products, Product::query()->count());
        $this->assertSame(1, User::query()->where('role', 'ADMIN')->count());
    }

    public function test_generates_password_when_missing(): void
    {
        $this->artisan('rotor:bootstrap', ['--no-migrate' => true, '--admin-email' => 'gen@rotor.test'])
            ->expectsOutputToContain('Contraseña generada')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'gen@rotor.test')->exists());
    }

    public function test_rejects_weak_password(): void
    {
        $this->artisan('rotor:bootstrap', ['--no-migrate' => true, '--admin-password' => 'corta'])->assertFailed();
        $this->assertSame(0, User::query()->count());
    }
}
