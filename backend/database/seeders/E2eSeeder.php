<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = (string) env('E2E_ADMIN_EMAIL');
        $adminPassword = (string) env('E2E_ADMIN_PASSWORD');
        $customerEmail = (string) env('E2E_CUSTOMER_EMAIL');
        $customerPassword = (string) env('E2E_CUSTOMER_PASSWORD');

        if ($adminEmail === '' || $customerEmail === '' || $adminPassword === '' || $customerPassword === '') {
            throw new RuntimeException('E2E_ADMIN_PASSWORD and E2E_CUSTOMER_PASSWORD are required locally.');
        }

        $admin = User::query()->create([
            'name' => 'Admin E2E',
            'email' => $adminEmail,
            'password' => $adminPassword,
            'role' => 'ADMIN',
        ]);

        $customerUser = User::query()->create([
            'name' => 'Customer E2E',
            'email' => $customerEmail,
            'password' => $customerPassword,
            'role' => 'CUSTOMER',
        ]);

        Customer::query()->create([
            'user_id' => $customerUser->id,
            'name' => 'Customer E2E',
            'email' => $customerEmail,
            'phone' => '+579000000001',
            'document_type' => 'CC',
            'document_number' => '9000000001',
        ]);

        if ($admin->id < 1 || $customerUser->customer === null) {
            throw new RuntimeException('E2E seeder failed to create required accounts.');
        }
    }
}
