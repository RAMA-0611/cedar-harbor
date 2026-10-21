<?php

namespace Tests\Feature\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_tables_exist(): void
    {
        foreach ([
            'users',
            'categories',
            'products',
            'customers',
            'carts',
            'cart_items',
            'stock_reservations',
            'orders',
            'order_items',
            'payments',
            'webhook_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_connection_is_pgsql_rotor_testing(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('rotor_testing', config('database.connections.pgsql.database'));
        $this->assertSame('rotor_testing', DB::selectOne('select current_database() as name')->name);
        $host = config('database.connections.pgsql.host');
        $this->assertTrue(in_array($host, ['127.0.0.1', 'localhost'], true));
    }

    public function test_users_have_role_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'role'));
    }
}
